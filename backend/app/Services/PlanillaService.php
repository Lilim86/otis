<?php

namespace App\Services;

use App\Models\Planilla;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanillaService
{
    /** Reemplaza el detalle y calcula líquidos y totales en el servidor. */
    public function guardarDetalles(Planilla $planilla, array $detalles): void
    {
        DB::transaction(function () use ($planilla, $detalles) {
            $filas = array_map(function ($d) {
                $descuentos = (float) ($d['descuentos_bs'] ?? 0);
                $haber = (float) $d['haber_basico_bs'];

                if ($descuentos > $haber) {
                    throw ValidationException::withMessages(['detalles' => 'Los descuentos no pueden superar el haber básico.']);
                }

                return [
                    'personal_id' => $d['personal_id'], 'haber_basico_bs' => $haber,
                    'descuentos_bs' => $descuentos, 'liquido_bs' => round($haber - $descuentos, 2),
                ];
            }, $detalles);

            $planilla->detalles()->delete();
            $planilla->detalles()->createMany($filas);

            $planilla->update([
                'total_haberes_bs' => round(array_sum(array_column($filas, 'haber_basico_bs')), 2),
                'total_descuentos_bs' => round(array_sum(array_column($filas, 'descuentos_bs')), 2),
                'total_liquido_bs' => round(array_sum(array_column($filas, 'liquido_bs')), 2),
            ]);
        });
    }
}
