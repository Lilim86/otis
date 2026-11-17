<?php

namespace App\Services\Ingenieria;

use App\Models\Contrato;
use App\Models\VisitaMantenimiento;
use App\Services\CronogramaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ingeniería de confiabilidad por edificio, con el historial de visitas:
 *
 *  - Fallas: visitas correctivas (las pide el cliente o las registra el personal).
 *  - Tasa de fallas λ = fallas / días observados (máximo 365).
 *  - MTBF = 1/λ (días entre fallas). MTTR = horas desde el aviso hasta que se cierra la reparación.
 *  - Disponibilidad A = MTBF / (MTBF + MTTR).
 *  - Probabilidad de que falle antes de la próxima preventiva, con el modelo exponencial:
 *    P = 1 − e^(−λ·t), t = días hasta la próxima visita preventiva. Si las preventivas están atrasadas
 *    la tasa sube un 50% por cada intervalo de atraso (sin mantenimiento el equipo se degrada).
 *
 * Con esto el cronograma deja de ser fijo: el sistema recomienda adelantar la visita a los de mayor riesgo.
 */
class ConfiabilidadService
{
    private const VENTANA = 365;

    public function __construct(private CronogramaService $cronograma) {}

    public function analizar(): array
    {
        $contratos = Contrato::with(['edificio:id,denominacion,latitud,longitud', 'edificio.equipos:id,edificio_id', 'empresa:id,denominacion'])
            ->where('estado_registro', '!=', 'ANL')->whereDate('fecha_fin', '>=', today())->get();
        $visitas = VisitaMantenimiento::whereIn('contrato_id', $contratos->pluck('id'))->where('situacion', '!=', 'CANCELADA')->get()->groupBy('contrato_id');
        $intervalo = (int) config('sofia.mantenimiento.intervalo_dias_habiles');

        $filas = $contratos->groupBy('edificio_id')->map(function (Collection $deEdificio) use ($visitas, $intervalo) {
            $c = $deEdificio->first();
            $propias = $deEdificio->flatMap(fn ($x) => $visitas[$x->id] ?? collect());
            $inicio = Carbon::parse($deEdificio->min('fecha_inicio'));
            $dias = max(30, min(self::VENTANA, (int) $inicio->diffInDays(today())));
            $desde = today()->subDays($dias);

            $fallas = $propias->where('tipo', 'CORRECTIVO')->filter(fn ($v) => $v->created_at->gte($desde));
            $reparadas = $fallas->where('situacion', 'REALIZADA')->filter(fn ($v) => self::llegada($v) !== null);
            $mttr = $reparadas->isNotEmpty() ? $reparadas->avg(fn ($v) => max(1, $v->created_at->diffInMinutes(self::llegada($v))) / 60) : null;

            // λ con suavizado (fallas + 0.5): un edificio sin fallas no tiene riesgo cero.
            $lambda = ($fallas->count() + 0.5) / $dias;
            $mtbf = 1 / $lambda;

            $preventivas = $propias->where('tipo', 'PREVENTIVO');
            $ultima = $preventivas->where('situacion', 'REALIZADA')->max(fn ($v) => $v->fecha_realizada?->toDateString());
            $proxima = $preventivas->whereIn('situacion', ['PROGRAMADA', 'REPROGRAMADA'])->filter(fn ($v) => $v->fecha_programada->gte(today()))->sortBy('fecha_programada')->first();
            $atrasadas = $preventivas->whereIn('situacion', ['PROGRAMADA', 'REPROGRAMADA'])->filter(fn ($v) => $v->fecha_programada->lt(today()))->count();

            $t = $proxima ? max(1, (int) today()->diffInDays($proxima->fecha_programada)) : $intervalo;
            $lambdaEfectiva = $lambda * (1 + 0.5 * $atrasadas);
            $probabilidad = 1 - exp(-$lambdaEfectiva * $t);
            $nivel = $probabilidad >= 0.5 ? 'ALTO' : ($probabilidad >= 0.25 ? 'MEDIO' : 'BAJO');

            return [
                'edificio_id' => $c->edificio_id, 'edificio' => $c->edificio?->denominacion, 'cliente' => $c->empresa?->denominacion,
                'equipos' => $c->edificio?->equipos->count() ?? 0, 'dias_observados' => $dias,
                'fallas' => $fallas->count(), 'tasa_fallas_anual' => round($lambda * 365, 2),
                'mtbf_dias' => round($mtbf, 1), 'mttr_horas' => $mttr !== null ? round($mttr, 1) : null,
                'disponibilidad' => $mttr !== null ? round($mtbf * 24 / ($mtbf * 24 + $mttr) * 100, 2) : null,
                'ultima_preventiva' => $ultima, 'proxima_preventiva' => $proxima?->fecha_programada->toDateString(), 'proxima_visita_id' => $proxima?->id,
                'dias_a_proxima' => $proxima ? $t : null, 'preventivas_atrasadas' => $atrasadas,
                'probabilidad_falla' => round($probabilidad * 100, 1), 'riesgo' => $nivel,
                'recomendacion' => match (true) {
                    $atrasadas > 0 => 'Hay preventivas atrasadas: programarlas ya.',
                    $nivel === 'ALTO' && $t > 3 => 'Adelantar la próxima preventiva.',
                    $nivel !== 'BAJO' && $fallas->count() >= 3 => 'Fallas repetidas: revisión de fondo (posible modernización).',
                    default => 'Mantener el cronograma.',
                },
            ];
        })->sortByDesc('probabilidad_falla')->values();

        $conMttr = $filas->whereNotNull('mttr_horas');

        return [
            'edificios' => $filas,
            'resumen' => [
                'edificios' => $filas->count(), 'alto' => $filas->where('riesgo', 'ALTO')->count(), 'medio' => $filas->where('riesgo', 'MEDIO')->count(),
                'fallas' => $filas->sum('fallas'),
                'mtbf_promedio_dias' => $filas->isNotEmpty() ? round($filas->avg('mtbf_dias'), 1) : null,
                'mttr_promedio_horas' => $conMttr->isNotEmpty() ? round($conMttr->avg('mttr_horas'), 1) : null,
                'disponibilidad_promedio' => $conMttr->isNotEmpty() ? round($conMttr->avg('disponibilidad'), 2) : null,
            ],
        ];
    }

    /** Adelanta la próxima preventiva del edificio al siguiente día hábil. */
    public function adelantar(int $edificioId): VisitaMantenimiento
    {
        $visita = VisitaMantenimiento::whereIn('contrato_id', Contrato::where('edificio_id', $edificioId)->pluck('id'))
            ->where('tipo', 'PREVENTIVO')->whereIn('situacion', ['PROGRAMADA', 'REPROGRAMADA'])->whereDate('fecha_programada', '>=', today())
            ->orderBy('fecha_programada')->firstOrFail();

        $visita->update([
            'fecha_programada' => $this->cronograma->sumarDiasHabiles(today(), 1)->toDateString(), 'situacion' => 'REPROGRAMADA', 'orden_ruta' => null,
            'descripcion' => trim(($visita->descripcion ?? '').' · Adelantada por riesgo de falla'),
        ]);

        return $visita;
    }

    /**
     * Cuándo llegó el técnico a una falla: al iniciar la visita o, si no se registró, al mediodía del día en que se cerró.
     * Si queda antes del aviso el registro es inconsistente (se cargó después) y no se cuenta.
     */
    private static function llegada(VisitaMantenimiento $v): ?Carbon
    {
        $llegada = $v->iniciada_en ?? $v->fecha_realizada?->copy()->setTime(12, 0);

        return $llegada && $v->created_at && $llegada->gte($v->created_at) ? $llegada : null;
    }

    // ------------------------------------------------------------------ nivel de servicio

    /**
     * Indicadores de servicio de un periodo: cumplimiento del cronograma preventivo, tiempo de respuesta a
     * fallas por prioridad contra su objetivo, y satisfacción del cliente.
     */
    public function nivelServicio(string $desde, string $hasta): array
    {
        $tolerancia = (int) config('sofia.ingenieria.tolerancia_dias');
        $objetivos = config('sofia.ingenieria.objetivo_horas');
        $visitas = VisitaMantenimiento::whereDate('fecha_programada', '>=', $desde)->whereDate('fecha_programada', '<=', min($hasta, today()->toDateString()))
            ->where('situacion', '!=', 'CANCELADA')->get();

        $preventivas = $visitas->where('tipo', 'PREVENTIVO');
        $vencidas = $preventivas->filter(fn ($v) => $v->fecha_programada->copy()->addDays($tolerancia)->lt(today()) || $v->situacion === 'REALIZADA');
        $aTiempo = $vencidas->filter(fn ($v) => $v->situacion === 'REALIZADA' && $v->fecha_realizada && $v->fecha_realizada->lte($v->fecha_programada->copy()->addDays($tolerancia)));

        $correctivas = $visitas->where('tipo', 'CORRECTIVO')->filter(fn ($v) => self::llegada($v) !== null);
        $horas = fn ($v) => $v->created_at->diffInMinutes(self::llegada($v)) / 60;
        $porPrioridad = collect($objetivos)->map(function ($objetivo, $p) use ($correctivas, $horas) {
            $grupo = $correctivas->where('prioridad', $p);

            return [
                'prioridad' => $p, 'objetivo_horas' => $objetivo, 'atendidas' => $grupo->count(),
                'respuesta_promedio_horas' => $grupo->isNotEmpty() ? round($grupo->avg($horas), 1) : null,
                'dentro_objetivo_pct' => $grupo->isNotEmpty() ? round($grupo->filter(fn ($v) => $horas($v) <= $objetivo)->count() / $grupo->count() * 100, 1) : null,
            ];
        })->values();
        $sinPrioridad = $correctivas->whereNull('prioridad');

        return [
            'desde' => $desde, 'hasta' => $hasta,
            'preventivas' => ['evaluadas' => $vencidas->count(), 'a_tiempo' => $aTiempo->count(), 'cumplimiento_pct' => $vencidas->isNotEmpty() ? round($aTiempo->count() / $vencidas->count() * 100, 1) : null],
            'correctivas' => [
                'atendidas' => $correctivas->count(),
                'respuesta_promedio_horas' => $correctivas->isNotEmpty() ? round($correctivas->avg($horas), 1) : null,
                'por_prioridad' => $porPrioridad,
                // Antes de la clasificación automática todas las fallas se atendían "por orden de llegada".
                'sin_clasificar' => ['atendidas' => $sinPrioridad->count(), 'respuesta_promedio_horas' => $sinPrioridad->isNotEmpty() ? round($sinPrioridad->avg($horas), 1) : null],
            ],
            'satisfaccion' => [
                'calificadas' => $visitas->whereNotNull('calificacion')->count(),
                'promedio' => $visitas->whereNotNull('calificacion')->isNotEmpty() ? round($visitas->whereNotNull('calificacion')->avg('calificacion'), 2) : null,
            ],
        ];
    }
}
