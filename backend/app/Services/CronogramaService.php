<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\VisitaMantenimiento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cronograma de mantenimiento preventivo de un contrato: una visita cada N días hábiles
 * (30 por defecto) desde el inicio del contrato hasta su fin. Hábil = de lunes a viernes y no feriado.
 */
class CronogramaService
{
    public function esHabil(Carbon $dia): bool
    {
        return ! $dia->isWeekend() && ! in_array($dia->format('m-d'), config('sofia.mantenimiento.feriados'), true);
    }

    /** Fecha que queda tras sumar $n días hábiles a $desde (el propio $desde no cuenta). */
    public function sumarDiasHabiles(Carbon $desde, int $n): Carbon
    {
        $dia = $desde->copy();
        while ($n > 0) {
            $dia->addDay();
            if ($this->esHabil($dia)) {
                $n--;
            }
        }

        return $dia;
    }

    /** Fechas de las visitas del contrato, en orden. */
    public function fechas(Contrato $contrato, ?int $intervalo = null): array
    {
        $intervalo ??= (int) config('sofia.mantenimiento.intervalo_dias_habiles');
        $fin = Carbon::parse($contrato->fecha_fin)->startOfDay();
        $fechas = [];

        for ($dia = $this->sumarDiasHabiles(Carbon::parse($contrato->fecha_inicio)->startOfDay(), $intervalo); $dia->lte($fin); $dia = $this->sumarDiasHabiles($dia, $intervalo)) {
            $fechas[] = $dia->copy();
        }

        return $fechas;
    }

    /**
     * Crea las visitas preventivas del contrato. Si ya tiene cronograma no duplica: con $regenerar
     * se rehacen solo las visitas que todavía están programadas (las realizadas se conservan).
     *
     * @return Collection<int, VisitaMantenimiento>
     */
    public function generar(Contrato $contrato, bool $regenerar = false, ?int $intervalo = null): Collection
    {
        return DB::transaction(function () use ($contrato, $regenerar, $intervalo) {
            $existentes = VisitaMantenimiento::where('contrato_id', $contrato->id)->where('tipo', 'PREVENTIVO');

            if ($existentes->exists() && ! $regenerar) {
                return $existentes->orderBy('fecha_programada')->get();
            }

            (clone $existentes)->where('situacion', 'PROGRAMADA')->forceDelete();
            $numero = (int) VisitaMantenimiento::where('contrato_id', $contrato->id)->where('tipo', 'PREVENTIVO')->max('nro_visita');
            $ultimaRealizada = VisitaMantenimiento::where('contrato_id', $contrato->id)->where('tipo', 'PREVENTIVO')->max('fecha_programada');

            foreach ($this->fechas($contrato, $intervalo) as $fecha) {
                if ($ultimaRealizada && $fecha->lte(Carbon::parse($ultimaRealizada))) {
                    continue;
                }
                VisitaMantenimiento::create([
                    'contrato_id' => $contrato->id, 'nro_visita' => ++$numero, 'tipo' => 'PREVENTIVO',
                    'fecha_programada' => $fecha->toDateString(), 'descripcion' => 'Mantenimiento preventivo programado',
                ]);
            }

            return VisitaMantenimiento::where('contrato_id', $contrato->id)->where('tipo', 'PREVENTIVO')->orderBy('fecha_programada')->get();
        });
    }
}
