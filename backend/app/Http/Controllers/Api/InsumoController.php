<?php

namespace App\Http\Controllers\Api;

use App\Models\Insumo;
use App\Services\InventarioService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** "Bienes y Servicios" del SOFIA. */
class InsumoController extends CrudController
{
    protected string $modelo = Insumo::class;

    protected bool $conAprobacion = true;

    protected array $con = ['grupo', 'subgrupo1', 'subgrupo2'];

    protected array $buscar = ['codigo', 'descripcion', 'marca', 'modelo', 'codigo_sin'];

    protected array $filtros = ['tipo', 'grupo_id', 'subgrupo1_id', 'subgrupo2_id', 'tipo_almacen', 'rotacion'];

    protected array $orden = [['codigo', 'asc']];

    protected function reglas(?Model $registro): array
    {
        $grupo = fn (int $nivel, ?string $campoPadre) => [
            'nullable',
            Rule::exists('grupos_bienes', 'id')->whereNull('deleted_at')->where('nivel', $nivel)
                ->when($campoPadre && request()->filled($campoPadre), fn ($r) => $r->where('padre_id', request()->input($campoPadre))),
        ];
        $precio = ['nullable', 'numeric', 'min:0', 'max:9999999999'];

        return [
            'codigo' => ['required', 'string', 'max:255', $this->unico('insumos', 'codigo', $registro)],
            'descripcion' => ['required', 'string', 'max:255'],
            'caracteristicas' => ['nullable', 'string'],
            'tipo' => ['required', 'string', 'max:100'],
            'grupo_id' => $grupo(1, null),
            'subgrupo1_id' => $grupo(2, 'grupo_id'),
            'subgrupo2_id' => $grupo(3, 'subgrupo1_id'),
            'unidad_medida' => ['nullable', Rule::in(array_keys(config('sofia.unidades_medida')))],
            'industria' => ['nullable', 'string', 'max:10'],
            'marca' => ['nullable', 'string', 'max:60'],
            'modelo' => ['nullable', 'string', 'max:60'],
            'codigo_referencia' => ['nullable', 'string', 'max:40'],
            'rotacion' => ['nullable', Rule::in(array_keys(config('sofia.rotacion')))],
            // El stock solo cambia con movimientos de almacén. Al crear, el stock inicial se registra como ingreso.
            'stock_actual' => ['nullable', 'numeric', 'min:0'],
            'almacen_id' => [Rule::requiredIf(! $registro && (float) request('stock_actual') > 0), 'nullable', $this->existe('almacenes')],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'precio_compra_bs' => $precio,
            'precio_compra_usd' => $precio,
            'precio_venta_base_bs' => $precio,
            'precio_venta_base_usd' => $precio,
            'precio_venta_cliente_bs' => $precio,
            'precio_venta_cliente_usd' => $precio,
            'tipo_almacen' => ['nullable', Rule::in(array_keys(config('sofia.tipos_almacen')))],
            'codigo_sin' => ['nullable', 'string', 'max:20'],
            'descripcion_sin' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** El stock se guarda solo por movimientos: aquí se ignora lo que llegue y nunca se escribe directo. */
    protected function datosParaGuardar(array $datos): array
    {
        unset($datos['stock_actual'], $datos['almacen_id']);

        return $datos;
    }

    protected function valoresPorDefecto(): array
    {
        return ['stock_actual' => 0];
    }

    protected function despuesDeGuardar(\Illuminate\Database\Eloquent\Model $registro, Request $request): void
    {
        $cantidad = (float) $request->input('stock_actual');

        if ($registro->wasRecentlyCreated && $cantidad > 0) {
            app(InventarioService::class)->registrar([
                'almacen_id' => $request->integer('almacen_id'), 'insumo_id' => $registro->id, 'tipo' => 'INGRESO', 'cantidad' => $cantidad,
                'costo_unitario_bs' => (float) $request->input('precio_compra_bs', 0), 'fecha' => today()->toDateString(),
                'motivo' => 'INICIAL', 'referencia' => 'Stock inicial', 'observacion' => 'Registrado al crear el bien',
            ]);
        }
    }
}
