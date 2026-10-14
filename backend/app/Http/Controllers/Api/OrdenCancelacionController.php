<?php

namespace App\Http\Controllers\Api;

use App\Models\Compra;
use App\Models\OrdenCancelacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Orden de cancelación (egresos): pagos a proveedores. */
class OrdenCancelacionController extends CrudController
{
    protected string $modelo = OrdenCancelacion::class;

    protected bool $conAprobacion = true;

    protected array $con = ['compra', 'factura', 'cuentaOrigen.banco'];

    protected array $buscar = ['nro_oc', 'comprobante_deposito', 'concepto'];

    protected array $filtros = ['compra_id', 'factura_proveedor_id', 'cuenta_origen_id'];

    protected array $fechas = ['fecha_pago'];

    protected array $orden = [['fecha_pago', 'desc']];

    protected array $padres = ['compra' => 'compra', 'factura' => 'factura del proveedor'];

    protected function reglas(?Model $registro): array
    {
        $compra = request()->input('compra_id', $registro?->compra_id);

        return [
            'compra_id' => ['nullable', $this->existe('compras')],
            'factura_proveedor_id' => ['nullable', Rule::exists('facturas_proveedor', 'id')->whereNull('deleted_at')->when($compra, fn ($r) => $r->where('compra_id', $compra))],
            'nro_oc' => ['required', 'string', 'max:40', $this->unico('ordenes_cancelacion', 'nro_oc', $registro)],
            'fecha_pago' => ['required', 'date'],
            'cuenta_origen_id' => ['nullable', $this->existe('cuentas_bancarias')],
            'comprobante_deposito' => ['nullable', 'string', 'max:40'],
            'pagado_bs' => ['required', 'numeric', 'min:0', function ($atributo, $valor, $fallar) use ($compra, $registro) {
                // No se puede pagar por encima del total de la compra.
                $total = $compra ? (float) Compra::whereKey($compra)->value('total_bs') : 0;
                if ($total > 0) {
                    $yaPagado = (float) OrdenCancelacion::where('compra_id', $compra)->where('estado_registro', '!=', 'ANL')
                        ->when($registro, fn ($q) => $q->whereKeyNot($registro->getKey()))->sum('pagado_bs');
                    if ($yaPagado + (float) $valor > $total + 0.001) {
                        $fallar('El pago supera el saldo pendiente de la compra.');
                    }
                }
            }],
            'pagado_usd' => ['nullable', 'numeric', 'min:0'],
            'concepto' => ['nullable', 'string', 'max:255'],
        ];
    }
}
