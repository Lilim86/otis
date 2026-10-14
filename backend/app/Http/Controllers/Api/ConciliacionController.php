<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CuentaBancaria;
use App\Models\IngresoTesoreria;
use App\Models\OrdenCancelacion;
use App\Models\TraspasoCuenta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Conciliación bancaria: compara el saldo según los libros (ingresos, pagos y traspasos aprobados)
 * con el saldo del extracto del banco para un período.
 */
class ConciliacionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'cuenta_bancaria_id' => ['required', Rule::exists('cuentas_bancarias', 'id')->whereNull('deleted_at')],
            'desde' => ['required', 'date'],
            'hasta' => ['required', 'date', 'after_or_equal:desde'],
            'saldo_extracto' => ['nullable', 'numeric'],
        ]);
        $cuenta = (int) $datos['cuenta_bancaria_id'];
        $rango = fn ($q, string $campo) => $q->whereDate($campo, '>=', $datos['desde'])->whereDate($campo, '<=', $datos['hasta']);

        $moneda = CuentaBancaria::whereKey($cuenta)->value('moneda');
        $columna = $moneda === 'USD' ? 'monto_usd' : 'monto_bs';

        $ingresos = (float) $rango(IngresoTesoreria::where('cuenta_bancaria_id', $cuenta)->where('estado_registro', 'APR'), 'fecha')->sum($columna);
        $pagos = (float) $rango(OrdenCancelacion::where('cuenta_origen_id', $cuenta)->where('estado_registro', 'APR'), 'fecha_pago')
            ->sum($moneda === 'USD' ? 'pagado_usd' : 'pagado_bs');
        $traspasosIn = (float) $rango(TraspasoCuenta::where('cuenta_destino_id', $cuenta)->where('estado_registro', 'APR'), 'fecha')->sum('monto');
        $traspasosOut = (float) $rango(TraspasoCuenta::where('cuenta_origen_id', $cuenta)->where('estado_registro', 'APR'), 'fecha')->sum('monto');

        $movimientoLibros = round($ingresos + $traspasosIn - $pagos - $traspasosOut, 2);

        return response()->json(['data' => [
            'cuenta_bancaria_id' => $cuenta,
            'moneda' => $moneda,
            'ingresos' => round($ingresos, 2),
            'traspasos_recibidos' => round($traspasosIn, 2),
            'pagos' => round($pagos, 2),
            'traspasos_enviados' => round($traspasosOut, 2),
            'movimiento_segun_libros' => $movimientoLibros,
            'saldo_extracto' => isset($datos['saldo_extracto']) ? (float) $datos['saldo_extracto'] : null,
            'diferencia' => isset($datos['saldo_extracto']) ? round((float) $datos['saldo_extracto'] - $movimientoLibros, 2) : null,
        ]]);
    }
}
