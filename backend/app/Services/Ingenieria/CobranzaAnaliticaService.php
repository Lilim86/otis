<?php

namespace App\Services\Ingenieria;

use App\Models\CuotaContrato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Analítica de cobranza con el historial de pago de cada cliente.
 *
 *  - Comportamiento: de cada cuota vencida se calcula cuántos días tardó en cubrirse (o cuántos lleva impaga).
 *  - Probabilidad de pago puntual con suavizado de Laplace: p = (puntuales + 1) / (cuotas + 2).
 *  - Puntaje de riesgo de mora (0-100) = 45·(1 − p) + 30·min(atraso/60, 1) + 25·min(saldo vencido / 3 cuotas, 1).
 *    Categorías: A (< 25), B (< 50), C (< 75), D.
 *  - Proyección de flujo de caja a 13 semanas: cada saldo pendiente entra en la semana en que el cliente suele
 *    pagar (vencimiento + su atraso típico) y pesado por su probabilidad de cobro.
 *  - Validación (backtest): se corta la historia hace 90 días, se proyecta con lo que se sabía entonces y se
 *    compara con lo que de verdad se cobró. Se contrasta con el método tradicional (todo se cobra al vencimiento).
 */
class CobranzaAnaliticaService
{
    private const SEMANAS = 13;

    /** Cuotas de contratos vigentes o pasados (sin anulados) con sus cobros válidos. */
    private function cuotas(): Collection
    {
        return CuotaContrato::with(['contrato:id,empresa_id,codigo_contrato,estado_registro', 'contrato.empresa:id,denominacion', 'cobranzas' => fn ($q) => $q->where('estado_registro', '!=', 'ANL')->orderBy('fecha_cobro')])
            ->whereHas('contrato', fn ($q) => $q->where('estado_registro', '!=', 'ANL'))->get();
    }

    /** Saldo de la cuota y día en que quedó cubierta, mirando solo los cobros hasta $corte. */
    private static function estado(CuotaContrato $c, Carbon $corte): array
    {
        $acumulado = 0.0;
        $cubierta = null;
        foreach ($c->cobranzas as $cobro) {
            $fecha = Carbon::parse($cobro->fecha_cobro);
            if ($fecha->gt($corte)) {
                break;
            }
            $acumulado += (float) $cobro->cobrado_bs;
            if (! $cubierta && $acumulado >= (float) $c->monto_bs - 0.01) {
                $cubierta = $fecha;
            }
        }

        return ['saldo' => max(0, round((float) $c->monto_bs - $acumulado, 2)), 'cubierta' => $cubierta];
    }

    /** Comportamiento de pago de cada empresa con lo que se sabía a la fecha $corte. */
    private function perfiles(Collection $cuotas, Carbon $corte): Collection
    {
        $gracia = (int) config('sofia.ingenieria.gracia_pago_dias');

        return $cuotas->filter(fn ($c) => $c->fecha_cuota->lt($corte))->groupBy(fn ($c) => $c->contrato->empresa_id)->map(function (Collection $grupo) use ($corte, $gracia) {
            $n = $grupo->count();
            $puntuales = 0;
            $atrasos = [];
            $saldoVencido = 0.0;
            $cobradas90 = 0;
            foreach ($grupo as $c) {
                $e = self::estado($c, $corte);
                $atraso = (int) $c->fecha_cuota->diffInDays($e['cubierta'] ?? $corte, false);
                $atrasos[] = max(0, $atraso);
                if ($e['cubierta'] && $atraso <= $gracia) {
                    $puntuales++;
                }
                if ($e['cubierta'] && $atraso <= 90) {
                    $cobradas90++;
                }
                $saldoVencido += $e['saldo'];
            }
            sort($atrasos);
            $p = ($puntuales + 1) / ($n + 2);
            $atrasoProm = array_sum($atrasos) / $n;
            $cuotaProm = max(1, $grupo->avg(fn ($c) => (float) $c->monto_bs));
            $puntaje = 100 * (0.45 * (1 - $p) + 0.30 * min($atrasoProm / 60, 1) + 0.25 * min($saldoVencido / ($cuotaProm * 3), 1));

            return [
                'cuotas_vencidas' => $n, 'puntuales' => $puntuales, 'prob_puntual' => $p,
                'prob_cobro_90' => ($cobradas90 + 1) / ($n + 2),
                'atraso_promedio' => $atrasoProm, 'atraso_mediano' => $atrasos[intdiv($n, 2)],
                'saldo_vencido' => round($saldoVencido, 2), 'puntaje' => round($puntaje, 1),
                'categoria' => $puntaje < 25 ? 'A' : ($puntaje < 50 ? 'B' : ($puntaje < 75 ? 'C' : 'D')),
            ];
        });
    }

    /** Perfil por defecto de un cliente sin historia: el promedio de la cartera. */
    private static function promedio(Collection $perfiles): array
    {
        return [
            'prob_puntual' => $perfiles->avg('prob_puntual') ?? 0.5, 'prob_cobro_90' => $perfiles->avg('prob_cobro_90') ?? 0.7,
            'atraso_mediano' => (int) round($perfiles->avg('atraso_mediano') ?? 0),
        ];
    }

    /**
     * Cuándo y cuánto se espera cobrar de cada saldo pendiente a la fecha $corte.
     *
     * @return Collection<int, array{empresa_id:int, fecha:Carbon, saldo:float, esperado:float, vence:Carbon}>
     */
    private function esperados(Collection $cuotas, Collection $perfiles, Carbon $corte, Carbon $hasta): Collection
    {
        $defecto = self::promedio($perfiles);

        return $cuotas->filter(fn ($c) => $c->fecha_cuota->lte($hasta))->map(function ($c) use ($perfiles, $defecto, $corte) {
            $e = self::estado($c, $corte);
            if ($e['saldo'] <= 0) {
                return null;
            }
            $perfil = $perfiles[$c->contrato->empresa_id] ?? $defecto;
            $fecha = $c->fecha_cuota->copy()->addDays($perfil['atraso_mediano']);
            $probabilidad = $perfil['prob_cobro_90'];
            if ($fecha->lt($corte)) {
                // Ya debía haberse pagado: se espera en las próximas semanas, con menos probabilidad cuanto más vieja.
                $vencida = (int) $c->fecha_cuota->diffInDays($corte);
                $probabilidad *= exp(-$vencida / 90);
                $fecha = $corte->copy()->addDays(7);
            }

            return ['empresa_id' => $c->contrato->empresa_id, 'fecha' => $fecha, 'vence' => $c->fecha_cuota, 'saldo' => $e['saldo'], 'esperado' => round($e['saldo'] * $probabilidad, 2)];
        })->filter()->values();
    }

    public function analizar(): array
    {
        $cuotas = $this->cuotas();
        $hoy = today();
        $perfiles = $this->perfiles($cuotas, $hoy);
        $nombres = $cuotas->mapWithKeys(fn ($c) => [$c->contrato->empresa_id => $c->contrato->empresa?->denominacion]);
        $proximas = $cuotas->filter(fn ($c) => $c->fecha_cuota->gte($hoy))->groupBy(fn ($c) => $c->contrato->empresa_id);

        $clientes = $perfiles->map(fn ($p, $empresaId) => [
            'empresa_id' => $empresaId, 'cliente' => $nombres[$empresaId] ?? "#{$empresaId}",
            'cuotas_vencidas' => $p['cuotas_vencidas'], 'puntuales' => $p['puntuales'],
            'prob_puntual' => round($p['prob_puntual'] * 100, 1), 'atraso_promedio_dias' => round($p['atraso_promedio'], 1),
            'saldo_vencido' => $p['saldo_vencido'], 'puntaje' => $p['puntaje'], 'categoria' => $p['categoria'],
            'por_vencer' => round((float) ($proximas[$empresaId] ?? collect())->sum(fn ($c) => self::estado($c, $hoy)['saldo']), 2),
            'accion' => match ($p['categoria']) {
                'A' => 'Recordatorio normal.',
                'B' => 'Recordatorio 5 días antes del vencimiento.',
                'C' => 'Llamada del cobrador y visita al edificio.',
                default => 'Gestión de cobro prioritaria; evaluar suspender servicios no críticos.',
            },
        ])->sortByDesc('puntaje')->values();

        return [
            'clientes' => $clientes,
            'resumen' => [
                'clientes' => $clientes->count(), 'saldo_vencido' => round($clientes->sum('saldo_vencido'), 2),
                'por_categoria' => collect(['A', 'B', 'C', 'D'])->mapWithKeys(fn ($k) => [$k => $clientes->where('categoria', $k)->count()]),
                'puntualidad_cartera' => $perfiles->isNotEmpty() ? round($perfiles->sum('puntuales') / max(1, $perfiles->sum('cuotas_vencidas')) * 100, 1) : null,
            ],
            'proyeccion' => $this->proyeccion($cuotas, $perfiles, $hoy),
            'validacion' => $this->validar($cuotas),
        ];
    }

    /** 13 semanas: lo programado según contrato contra lo que se espera cobrar de verdad. */
    private function proyeccion(Collection $cuotas, Collection $perfiles, Carbon $hoy): array
    {
        $fin = $hoy->copy()->addWeeks(self::SEMANAS);
        $esperados = $this->esperados($cuotas, $perfiles, $hoy, $fin);
        $semanas = collect(range(0, self::SEMANAS - 1))->map(function ($i) use ($hoy, $esperados) {
            $desde = $hoy->copy()->addWeeks($i);
            $hasta = $desde->copy()->addDays(6);
            $programado = $esperados->filter(fn ($e) => $i === 0 ? $e['vence']->lte($hasta) : $e['vence']->between($desde, $hasta))->sum('saldo');
            $esperado = $esperados->filter(fn ($e) => $e['fecha']->between($desde, $hasta))->sum('esperado');

            return ['semana' => $i + 1, 'desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString(), 'programado' => round($programado, 2), 'esperado' => round($esperado, 2)];
        });

        return [
            'semanas' => $semanas,
            'total_programado' => round($semanas->sum('programado'), 2), 'total_esperado' => round($semanas->sum('esperado'), 2),
            // Lo que probablemente no entra en el trimestre: hay que gestionarlo o provisionarlo.
            'brecha' => round($semanas->sum('programado') - $semanas->sum('esperado'), 2),
        ];
    }

    /** Backtest a 90 días: error del modelo contra el método tradicional. */
    private function validar(Collection $cuotas): ?array
    {
        $corte = today()->subDays(90);
        $hoy = today();
        $perfiles = $this->perfiles($cuotas, $corte);
        if ($perfiles->isEmpty()) {
            return null;
        }

        $esperados = $this->esperados($cuotas, $perfiles, $corte, $hoy)->filter(fn ($e) => $e['fecha']->lte($hoy));
        $tradicional = $cuotas->filter(fn ($c) => $c->fecha_cuota->lte($hoy))->sum(fn ($c) => self::estado($c, $corte)['saldo']);
        $real = $cuotas->filter(fn ($c) => $c->fecha_cuota->lte($hoy))->sum(fn ($c) => $c->cobranzas->filter(fn ($b) => Carbon::parse($b->fecha_cobro)->between($corte->copy()->addDay(), $hoy))->sum('cobrado_bs'));
        if ($real <= 0) {
            return null;
        }
        $modelo = $esperados->sum('esperado');

        return [
            'corte' => $corte->toDateString(), 'hasta' => $hoy->toDateString(),
            'cobrado_real' => round((float) $real, 2), 'proyectado_modelo' => round($modelo, 2), 'proyectado_tradicional' => round((float) $tradicional, 2),
            'error_modelo_pct' => round(abs($modelo - $real) / $real * 100, 1),
            'error_tradicional_pct' => round(abs($tradicional - $real) / $real * 100, 1),
        ];
    }
}
