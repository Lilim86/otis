<?php

namespace App\Services;

use App\Models\Compra;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompraService
{
    public function __construct(private InventarioService $inventario) {}

    /** Reemplaza el detalle y recalcula los totales (el cliente no envía totales). */
    public function guardarDetalles(Compra $compra, array $detalles): void
    {
        DB::transaction(function () use ($compra, $detalles) {
            $compra->detalles()->delete();
            $compra->detalles()->createMany(array_map(fn ($d) => [
                'insumo_id' => $d['insumo_id'], 'cantidad' => $d['cantidad'], 'precio_unitario_bs' => $d['precio_unitario_bs'],
            ], $detalles));

            $totalBs = round($compra->detalles()->get()->sum(fn ($d) => $d->cantidad * $d->precio_unitario_bs), 2);
            $compra->update([
                'total_bs' => $totalBs,
                'total_usd' => round($totalBs / (float) config('sofia.tipo_cambio_usd'), 2),
            ]);
        });
    }

    /** Aprobar la compra ingresa lo comprado al almacén elegido. */
    public function aprobar(Compra $compra): Compra
    {
        if (! $compra->almacen_id) {
            throw ValidationException::withMessages(['almacen_id' => 'Elige el almacén donde ingresará lo comprado antes de aprobar.']);
        }

        if (! $compra->detalles()->exists()) {
            throw ValidationException::withMessages(['detalles' => 'La compra no tiene bienes para ingresar.']);
        }

        return DB::transaction(function () use ($compra) {
            $compra->aprobar();

            foreach ($compra->detalles as $detalle) {
                $this->inventario->registrar([
                    'almacen_id' => $compra->almacen_id,
                    'insumo_id' => $detalle->insumo_id,
                    'tipo' => 'INGRESO',
                    'cantidad' => $detalle->cantidad,
                    'costo_unitario_bs' => $detalle->precio_unitario_bs,
                    'fecha' => $compra->fecha_compra,
                    'referencia' => $compra->codigo_compra,
                    'compra_id' => $compra->id,
                ]);
            }

            return $compra;
        });
    }

    /** Anular una compra aprobada revierte sus ingresos, siempre que el pago no haya empezado. */
    public function anular(Compra $compra): Compra
    {
        if ($compra->ordenesCancelacion()->where('estado_registro', 'APR')->exists()) {
            throw ValidationException::withMessages(['estado_registro' => 'No se puede anular: tiene órdenes de cancelación (pagos) aprobadas.']);
        }

        return DB::transaction(function () use ($compra) {
            if ($compra->estado_registro === 'APR') {
                foreach ($compra->movimientos()->where('estado_registro', '!=', 'ANL')->get() as $movimiento) {
                    $this->inventario->anular($movimiento);
                }
            }

            $compra->anularRegistro();

            return $compra;
        });
    }
}
