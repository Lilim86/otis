<?php

namespace App\Services;

use App\Models\OrdenMantenimiento;
use App\Models\Repuesto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrdenMantenimientoService
{
    /**
     * Finaliza la orden y descuenta del inventario el repuesto utilizado.
     * Si no alcanza el stock, no se finaliza nada.
     */
    public function finalizar(OrdenMantenimiento $orden): OrdenMantenimiento
    {
        if ($orden->situacion === 'Finalizado') {
            return $orden;
        }

        return DB::transaction(function () use ($orden) {
            if ($orden->repuesto_id && $orden->cantidad_utilizada > 0) {
                $repuesto = Repuesto::lockForUpdate()->find($orden->repuesto_id);

                if (! $repuesto || $repuesto->stock < $orden->cantidad_utilizada) {
                    throw ValidationException::withMessages([
                        'cantidad_utilizada' => 'Stock insuficiente del repuesto para finalizar la orden.',
                    ]);
                }

                $repuesto->decrement('stock', $orden->cantidad_utilizada);
            }

            $orden->update(['situacion' => 'Finalizado']);

            return $orden;
        });
    }
}
