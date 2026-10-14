<?php

namespace App\Http\Controllers\Api;

use App\Models\FacturaProveedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class FacturaProveedorController extends CrudController
{
    protected string $modelo = FacturaProveedor::class;

    protected bool $conAprobacion = true;

    protected array $con = ['compra'];

    protected array $buscar = ['nro_factura', 'concepto'];

    protected array $filtros = ['compra_id'];

    protected array $fechas = ['fecha_factura'];

    protected array $orden = [['fecha_factura', 'desc']];

    protected array $dependientes = ['ordenesCancelacion' => 'órdenes de cancelación'];

    protected array $padres = ['compra' => 'compra'];

    protected function reglas(?Model $registro): array
    {
        $compra = request()->input('compra_id', $registro?->compra_id);

        return [
            'compra_id' => ['required', $this->existe('compras')],
            'fecha_factura' => ['required', 'date', 'before_or_equal:today'],
            'nro_factura' => [
                'required', 'string', 'max:40',
                Rule::unique('facturas_proveedor', 'nro_factura')->where('compra_id', $compra)->whereNull('deleted_at')->ignore($registro?->getKey()),
            ],
            'importe_bs' => ['required', 'numeric', 'gt:0'],
            'concepto' => ['nullable', 'string', 'max:255'],
        ];
    }
}
