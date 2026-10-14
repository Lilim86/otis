<?php

namespace App\Services;

use App\Models\Insumo;
use App\Models\MovimientoAlmacen;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioService
{
    /** Saldo físico de un bien en un almacén: ingresos menos salidas, sin contar movimientos anulados. */
    public function saldo(int $almacenId, int $insumoId): float
    {
        $base = MovimientoAlmacen::where('almacen_id', $almacenId)->where('insumo_id', $insumoId)->where('estado_registro', '!=', 'ANL');

        return (float) (clone $base)->where('tipo', 'INGRESO')->sum('cantidad')
            - (float) (clone $base)->where('tipo', 'SALIDA')->sum('cantidad');
    }

    /** Registra el movimiento y actualiza el stock del bien. Una salida sin saldo suficiente se rechaza. */
    public function registrar(array $datos): MovimientoAlmacen
    {
        return DB::transaction(function () use ($datos) {
            $insumo = Insumo::lockForUpdate()->findOrFail($datos['insumo_id']);

            if ($datos['tipo'] === 'SALIDA' && $this->saldo($datos['almacen_id'], $insumo->id) < (float) $datos['cantidad']) {
                throw ValidationException::withMessages(['cantidad' => 'Saldo insuficiente en el almacén para esta salida.']);
            }

            $movimiento = MovimientoAlmacen::create($datos);
            $this->ajustarStock($insumo, $datos['tipo'], (float) $datos['cantidad']);

            return $movimiento;
        });
    }

    /** Anula el movimiento y revierte su efecto en el stock. */
    public function anular(MovimientoAlmacen $movimiento): MovimientoAlmacen
    {
        return DB::transaction(function () use ($movimiento) {
            $insumo = Insumo::lockForUpdate()->findOrFail($movimiento->insumo_id);

            // Anular un ingreso equivale a sacar esa cantidad: debe haber saldo para hacerlo.
            if ($movimiento->tipo === 'INGRESO' && $this->saldo($movimiento->almacen_id, $insumo->id) < (float) $movimiento->cantidad) {
                throw ValidationException::withMessages(['cantidad' => 'No se puede anular: parte de ese ingreso ya salió del almacén.']);
            }

            $movimiento->anularRegistro();
            $this->ajustarStock($insumo, $movimiento->tipo === 'INGRESO' ? 'SALIDA' : 'INGRESO', (float) $movimiento->cantidad);

            return $movimiento;
        });
    }

    /**
     * Inventario por bien de un almacén (reporte REP-0069 del SOFIA): cantidad ingresada, salida,
     * saldo físico y valor al costo promedio de los ingresos.
     */
    public function inventario(int $almacenId): array
    {
        $movimientos = MovimientoAlmacen::with('insumo:id,codigo,descripcion')
            ->where('almacen_id', $almacenId)->where('estado_registro', '!=', 'ANL')->get();

        return $movimientos->groupBy('insumo_id')->map(function ($filas) {
            $ingresos = $filas->where('tipo', 'INGRESO');
            $ingresada = (float) $ingresos->sum('cantidad');
            $salida = (float) $filas->where('tipo', 'SALIDA')->sum('cantidad');
            $costoPromedio = $ingresada > 0 ? $ingresos->sum(fn ($m) => $m->cantidad * $m->costo_unitario_bs) / $ingresada : 0;
            $saldo = $ingresada - $salida;

            return [
                'codigo' => $filas->first()->insumo->codigo,
                'descripcion' => $filas->first()->insumo->descripcion,
                'cantidad_ingresada' => round($ingresada, 2),
                'cantidad_salida' => round($salida, 2),
                'saldo_fisico' => round($saldo, 2),
                'costo_promedio_bs' => round($costoPromedio, 2),
                'valor_bs' => round($saldo * $costoPromedio, 2),
            ];
        })->sortBy('codigo')->values()->all();
    }

    private function ajustarStock(Insumo $insumo, string $tipo, float $cantidad): void
    {
        $tipo === 'INGRESO' ? $insumo->increment('stock_actual', $cantidad) : $insumo->decrement('stock_actual', $cantidad);
    }
}
