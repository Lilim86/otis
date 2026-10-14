<?php

namespace App\Http\Controllers\Api;

use App\Models\MovimientoAlmacen;
use App\Services\InventarioService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Entradas y salidas de almacén. Un movimiento afecta el stock al registrarse y no se edita:
 * si hubo un error se anula (ANL), lo que revierte su efecto, y se registra uno nuevo.
 */
class MovimientoAlmacenController extends CrudController
{
    protected string $modelo = MovimientoAlmacen::class;

    protected bool $conAprobacion = true;

    protected array $con = ['almacen', 'insumo'];

    protected array $buscar = ['referencia', 'observacion'];

    protected array $filtros = ['almacen_id', 'insumo_id', 'tipo', 'compra_id'];

    protected array $fechas = ['fecha'];

    protected array $orden = [['fecha', 'desc'], ['id', 'desc']];

    protected function reglas(?Model $registro): array
    {
        return [
            'almacen_id' => ['required', $this->existe('almacenes')],
            'insumo_id' => ['required', $this->existe('insumos')],
            'tipo' => ['required', Rule::in(['INGRESO', 'SALIDA'])],
            'cantidad' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'costo_unitario_bs' => ['nullable', 'numeric', 'min:0'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string'],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $movimiento = app(InventarioService::class)->registrar($request->validate($this->reglas(null)));

        return $this->responder($movimiento->refresh())->setStatusCode(201);
    }

    public function update(Request $request, string $id)
    {
        abort(405, 'Un movimiento de almacén no se edita: anúlalo y registra uno nuevo.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        return response()->json(['message' => 'Un movimiento de almacén no se elimina: anúlalo (ANL) para revertir su efecto.'], 409);
    }

    public function anular(string $id)
    {
        return $this->recurso(app(InventarioService::class)->anular($this->buscarRegistro($id)));
    }
}
