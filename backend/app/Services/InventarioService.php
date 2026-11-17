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

            if ($datos['tipo'] === 'SALIDA' && ($saldo = $this->saldo($datos['almacen_id'], $insumo->id)) < (float) $datos['cantidad']) {
                throw ValidationException::withMessages(['cantidad' => 'Saldo insuficiente en el almacén para esta salida (hay '.(float) $saldo.').']);
            }

            // Una devolución solo puede ser de lo que esa persona tiene prestado.
            if (($datos['motivo'] ?? null) === 'DEVOLUCION') {
                $debe = $this->enPoderDe($datos['personal_id'] ?? 0, $insumo->id);
                if ($debe < (float) $datos['cantidad']) {
                    throw ValidationException::withMessages(['cantidad' => 'Esa persona tiene prestado '.(float) $debe.' de este bien: no puede devolver más.']);
                }
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

    /** Cuánto de un bien tiene prestado una persona: préstamos menos devoluciones. */
    public function enPoderDe(int $personalId, int $insumoId): float
    {
        $base = MovimientoAlmacen::where('personal_id', $personalId)->where('insumo_id', $insumoId)->where('estado_registro', '!=', 'ANL');

        return (float) (clone $base)->where('motivo', 'PRESTAMO')->sum('cantidad') - (float) (clone $base)->where('motivo', 'DEVOLUCION')->sum('cantidad');
    }

    /** Herramientas que siguen fuera del almacén: quién las tiene, cuántas y desde cuándo. */
    public function herramientasEnPoder(): array
    {
        $filas = MovimientoAlmacen::with(['personal:id,nombres,apellidos', 'insumo:id,codigo,descripcion', 'almacen:id,nombre'])
            ->whereIn('motivo', ['PRESTAMO', 'DEVOLUCION'])->where('estado_registro', '!=', 'ANL')->whereNotNull('personal_id')->get();

        return $filas->groupBy(fn ($m) => $m->personal_id.'-'.$m->insumo_id)->map(function ($grupo) {
            $debe = (float) $grupo->where('motivo', 'PRESTAMO')->sum('cantidad') - (float) $grupo->where('motivo', 'DEVOLUCION')->sum('cantidad');
            $primero = $grupo->where('motivo', 'PRESTAMO')->sortBy('fecha')->first();

            return [
                'personal_id' => $grupo->first()->personal_id,
                'persona' => trim($grupo->first()->personal?->nombres.' '.$grupo->first()->personal?->apellidos),
                'insumo_id' => $grupo->first()->insumo_id,
                'codigo' => $grupo->first()->insumo?->codigo,
                'herramienta' => $grupo->first()->insumo?->descripcion,
                'cantidad' => round($debe, 2),
                'desde' => $primero?->fecha?->toDateString(),
                'dias' => $primero ? (int) $primero->fecha->diffInDays(today()) : null,
            ];
        })->filter(fn ($f) => $f['cantidad'] > 0)->sortByDesc('dias')->values()->all();
    }

    /** Bienes que llegaron o bajaron del stock mínimo. */
    public function stockBajo(): array
    {
        return Insumo::where('stock_minimo', '>', 0)->whereColumn('stock_actual', '<=', 'stock_minimo')->where('estado', true)
            ->orderBy('codigo')->get(['id', 'codigo', 'descripcion', 'stock_actual', 'stock_minimo'])
            ->map(fn ($i) => ['id' => $i->id, 'codigo' => $i->codigo, 'descripcion' => $i->descripcion, 'stock_actual' => (float) $i->stock_actual, 'stock_minimo' => (float) $i->stock_minimo])->all();
    }

    /** Dónde hay existencias de un bien (para elegir de qué almacén sacarlo). */
    public function existencias(int $insumoId): array
    {
        return MovimientoAlmacen::with('almacen:id,codigo,nombre')->where('insumo_id', $insumoId)->where('estado_registro', '!=', 'ANL')->get()
            ->groupBy('almacen_id')->map(fn ($f) => [
                'almacen_id' => $f->first()->almacen_id, 'almacen' => $f->first()->almacen?->nombre,
                'saldo' => round((float) $f->where('tipo', 'INGRESO')->sum('cantidad') - (float) $f->where('tipo', 'SALIDA')->sum('cantidad'), 2),
            ])->filter(fn ($e) => $e['saldo'] > 0)->values()->all();
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
