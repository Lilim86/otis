<?php

namespace App\Http\Controllers\Api;

use App\Models\Compra;
use App\Services\CompraService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class CompraController extends CrudController
{
    protected string $modelo = Compra::class;

    protected bool $conAprobacion = true;

    // Una compra aprobada ya generó ingresos al almacén: solo se puede anular.
    protected array $estadosSinEdicion = ['APR', 'ANL'];

    protected array $con = ['proveedor', 'proveedorEmpresa', 'regional', 'almacen', 'detalles.insumo', 'facturas', 'ordenesCancelacion'];

    protected array $buscar = ['codigo_compra', 'unidad_oficina', 'observacion'];

    protected array $filtros = ['proveedor_id', 'proveedor_empresa_id', 'regional_id', 'almacen_id', 'unidad_oficina'];

    protected array $fechas = ['fecha_compra'];

    protected array $orden = [['fecha_compra', 'desc']];

    protected array $dependientes = ['facturas' => 'facturas del proveedor', 'ordenesCancelacion' => 'órdenes de cancelación', 'movimientos' => 'movimientos de almacén'];

    protected array $padres = ['regional' => 'regional', 'almacen' => 'almacén'];

    protected function reglas(?Model $registro): array
    {
        return [
            'codigo_compra' => ['required', 'string', 'max:30', $this->unico('compras', 'codigo_compra', $registro)],
            'proveedor_id' => ['nullable', 'required_without:proveedor_empresa_id', $this->existe('personas')],
            'proveedor_empresa_id' => ['nullable', 'required_without:proveedor_id', $this->existe('empresas')],
            'regional_id' => ['nullable', $this->existe('departamentos')],
            'unidad_oficina' => ['nullable', 'string', 'max:20'],
            'almacen_id' => ['nullable', $this->existe('almacenes')],
            'fecha_compra' => ['required', 'date'],
            'observacion' => ['nullable', 'string'],
            'detalles' => ['nullable', 'array'],
            'detalles.*.insumo_id' => ['required', 'distinct', $this->existe('insumos')],
            'detalles.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'detalles.*.precio_unitario_bs' => ['required', 'numeric', 'min:0'],
        ];
    }

    protected function datosParaGuardar(array $datos): array
    {
        unset($datos['detalles']);

        return $datos;
    }

    protected function despuesDeGuardar(Model $registro, Request $request): void
    {
        if ($request->has('detalles')) {
            app(CompraService::class)->guardarDetalles($registro, $request->input('detalles', []));
        }
    }

    public function aprobar(string $id)
    {
        return $this->recurso(app(CompraService::class)->aprobar($this->buscarRegistro($id)));
    }

    public function anular(string $id)
    {
        return $this->recurso(app(CompraService::class)->anular($this->buscarRegistro($id)));
    }
}
