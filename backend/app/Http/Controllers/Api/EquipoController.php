<?php

namespace App\Http\Controllers\Api;

use App\Models\Equipo;
use App\Services\PreciosEquipoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EquipoController extends CrudController
{
    protected string $modelo = Equipo::class;

    protected bool $conAprobacion = true;

    protected array $con = ['edificio', 'grupo', 'subgrupo1', 'subgrupo2', 'insumosBasicos'];

    protected array $buscar = ['codigo', 'descripcion', 'marca', 'modelo'];

    protected array $filtros = ['edificio_id', 'grupo_id', 'subgrupo1_id', 'subgrupo2_id', 'marca', 'rotacion'];

    protected array $orden = [['codigo', 'asc']];

    protected array $padres = ['edificio' => 'edificio'];

    protected function reglas(?Model $registro): array
    {
        $grupo = fn (int $nivel, ?string $campoPadre) => [
            'nullable',
            Rule::exists('grupos_bienes', 'id')->whereNull('deleted_at')->where('nivel', $nivel)
                ->when($campoPadre && request()->filled($campoPadre), fn ($r) => $r->where('padre_id', request()->input($campoPadre))),
        ];
        $precio = ['nullable', 'numeric', 'min:0', 'max:9999999999'];

        return [
            'codigo' => ['required', 'string', 'max:255', $this->unico('equipos', 'codigo', $registro)],
            'descripcion' => ['required', 'string', 'max:255'],
            'caracteristicas' => ['nullable', 'string'],
            'edificio_id' => ['nullable', $this->existe('edificios')],
            'grupo_id' => $grupo(1, null),
            'subgrupo1_id' => $grupo(2, 'grupo_id'),
            'subgrupo2_id' => $grupo(3, 'subgrupo1_id'),
            'unidad_medida' => ['nullable', Rule::in(array_keys(config('sofia.unidades_medida')))],
            'industria' => ['nullable', 'string', 'max:255'],
            'marca' => ['nullable', 'string', 'max:255'],
            'modelo' => ['nullable', 'string', 'max:255'],
            'paradas' => ['nullable', 'integer', 'min:1'],
            'codigo_referencia' => ['nullable', 'string', 'max:40'],
            'rotacion' => ['nullable', Rule::in(array_keys(config('sofia.rotacion')))],
            'precio_compra_fob_bs' => $precio,
            'precio_compra_fob_usd' => $precio,
            'precio_venta_base_bs' => $precio,
            'precio_venta_base_usd' => $precio,
            'precio_venta_cliente_bs' => $precio,
            'precio_venta_cliente_usd' => $precio,
            'horas_servicio' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'insumos_basicos' => ['nullable', 'array'],
            'insumos_basicos.*.insumo_id' => ['required', 'distinct', $this->existe('insumos')],
            'insumos_basicos.*.cantidad' => ['required', 'numeric', 'gt:0'],
        ];
    }

    protected function datosParaGuardar(array $datos): array
    {
        unset($datos['insumos_basicos']);

        return $datos;
    }

    protected function despuesDeGuardar(Model $registro, Request $request): void
    {
        if (! $request->has('insumos_basicos')) {
            return;
        }

        $registro->insumosBasicos()->sync(
            collect($request->input('insumos_basicos', []))->mapWithKeys(
                fn ($fila) => [$fila['insumo_id'] => ['cantidad' => $fila['cantidad']]]
            )->all()
        );
    }

    /** Botón "Cálculos": precios de venta a partir del precio de compra FOB. */
    public function calcularPrecios(Request $request, PreciosEquipoService $servicio): JsonResponse
    {
        $datos = $request->validate([
            'precio_compra_fob_bs' => ['required_without:precio_compra_fob_usd', 'nullable', 'numeric', 'min:0'],
            'precio_compra_fob_usd' => ['required_without:precio_compra_fob_bs', 'nullable', 'numeric', 'min:0'],
        ]);

        return response()->json(['data' => $servicio->calcular(
            isset($datos['precio_compra_fob_bs']) ? (float) $datos['precio_compra_fob_bs'] : null,
            isset($datos['precio_compra_fob_usd']) ? (float) $datos['precio_compra_fob_usd'] : null,
        )]);
    }
}
