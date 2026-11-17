<?php

namespace App\Services;

use App\Models\Personal;
use App\Models\User;
use App\Models\VisitaMantenimiento;

/** Cronograma visto desde los técnicos: dónde está cada uno, cuánto trabajo tiene y qué visitas no tienen quién las haga. */
class TableroTecnicosService
{
    private const ABIERTAS = ['PROGRAMADA', 'REPROGRAMADA', 'SOLICITADA', 'EN_CURSO'];

    /** Técnicos activos: quienes tienen usuario de campo o ya figuran en alguna visita. */
    public function tecnicos()
    {
        $ids = User::whereNotNull('personal_id')->pluck('personal_id')
            ->merge(VisitaMantenimiento::whereNotNull('tecnico_id')->distinct()->pluck('tecnico_id'))->unique();

        return Personal::whereIn('id', $ids)->where('estado', true)->orderBy('nombres')->get();
    }

    public function armar(string $desde, string $hasta): array
    {
        $visitas = VisitaMantenimiento::with(['contrato.empresa:id,denominacion', 'contrato.edificio:id,denominacion', 'tecnico:id,nombres,apellidos'])
            ->whereDate('fecha_programada', '>=', $desde)->whereDate('fecha_programada', '<=', $hasta)->where('situacion', '!=', 'CANCELADA')
            ->orderBy('fecha_programada')->get();

        $fila = fn (VisitaMantenimiento $v) => [
            'id' => $v->id, 'fecha' => $v->fecha_programada->toDateString(), 'tipo' => $v->tipo, 'situacion' => $v->situacion,
            'cliente' => $v->contrato?->empresa?->denominacion, 'edificio' => $v->contrato?->edificio?->denominacion,
            'tecnico_id' => $v->tecnico_id, 'tecnico' => $v->tecnico ? trim("{$v->tecnico->nombres} {$v->tecnico->apellidos}") : null,
            'iniciada_en' => $v->iniciada_en?->toIso8601String(),
        ];
        $abiertas = $visitas->whereIn('situacion', self::ABIERTAS);

        $tecnicos = $this->tecnicos()->map(function (Personal $t) use ($visitas, $abiertas, $fila) {
            $propias = $visitas->where('tecnico_id', $t->id);
            $enCurso = $propias->firstWhere('situacion', 'EN_CURSO');

            return [
                'id' => $t->id, 'nombre' => trim("{$t->nombres} {$t->apellidos}"),
                'estado' => $enCurso ? 'EN_SITIO' : ($abiertas->where('tecnico_id', $t->id)->filter(fn ($v) => $v->fecha_programada->isToday())->isNotEmpty() ? 'CON_RUTA_HOY' : 'LIBRE'),
                'en_curso' => $enCurso ? $fila($enCurso) : null,
                'hoy' => $abiertas->where('tecnico_id', $t->id)->filter(fn ($v) => $v->fecha_programada->isToday())->count(),
                'pendientes' => $abiertas->where('tecnico_id', $t->id)->count(),
                'atrasadas' => $abiertas->where('tecnico_id', $t->id)->filter(fn ($v) => $v->fecha_programada->isPast() && ! $v->fecha_programada->isToday())->count(),
                'realizadas' => $propias->where('situacion', 'REALIZADA')->count(),
                'proximas' => $abiertas->where('tecnico_id', $t->id)->take(5)->map($fila)->values(),
            ];
        })->values();

        return [
            'desde' => $desde, 'hasta' => $hasta,
            'resumen' => [
                'visitas' => $visitas->count(), 'realizadas' => $visitas->where('situacion', 'REALIZADA')->count(),
                'en_curso' => $visitas->where('situacion', 'EN_CURSO')->count(),
                'sin_tecnico' => $abiertas->whereNull('tecnico_id')->count(),
                'atrasadas' => $abiertas->filter(fn ($v) => $v->fecha_programada->isPast() && ! $v->fecha_programada->isToday())->count(),
            ],
            'tecnicos' => $tecnicos,
            'sin_tecnico' => $abiertas->whereNull('tecnico_id')->take(100)->map($fila)->values(),
            'visitas' => $visitas->take(400)->map($fila)->values(),
        ];
    }

    /** @return array{asignadas:int,sin_cupo:int} */
    public function autoasignar(string $desde, string $hasta): array
    {
        $tecnicos = $this->tecnicos();
        $maximo = (int) config('sofia.mantenimiento.max_visitas_por_dia');
        $asignadas = $sinCupo = 0;

        if ($tecnicos->isEmpty()) {
            return ['asignadas' => 0, 'sin_cupo' => VisitaMantenimiento::whereNull('tecnico_id')->whereIn('situacion', self::ABIERTAS)->count()];
        }

        $pendientes = VisitaMantenimiento::whereNull('tecnico_id')->whereIn('situacion', ['PROGRAMADA', 'REPROGRAMADA', 'SOLICITADA'])
            ->whereDate('fecha_programada', '>=', $desde)->whereDate('fecha_programada', '<=', $hasta)->orderBy('fecha_programada')->get();

        foreach ($pendientes as $visita) {
            $fecha = $visita->fecha_programada->toDateString();
            $carga = $tecnicos->mapWithKeys(fn ($t) => [$t->id => [
                'dia' => VisitaMantenimiento::where('tecnico_id', $t->id)->whereDate('fecha_programada', $fecha)->whereNotIn('situacion', ['REALIZADA', 'CANCELADA'])->count(),
                'total' => VisitaMantenimiento::where('tecnico_id', $t->id)->whereIn('situacion', self::ABIERTAS)->count(),
            ]])->filter(fn ($c) => $c['dia'] < $maximo)->sortBy([['total', 'asc'], ['dia', 'asc']]);

            if ($carga->isEmpty()) {
                $sinCupo++;

                continue;
            }
            $visita->update(['tecnico_id' => $carga->keys()->first()]);
            $asignadas++;
        }

        return ['asignadas' => $asignadas, 'sin_cupo' => $sinCupo];
    }
}
