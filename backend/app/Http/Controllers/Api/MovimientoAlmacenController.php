<?php

namespace App\Http\Controllers\Api;

use App\Models\Almacen;
use App\Models\Insumo;
use App\Models\MovimientoAlmacen;
use App\Services\InventarioService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Entradas y salidas de almacén. Un movimiento afecta el stock al registrarse y no se edita:
 * si hubo un error se anula (ANL), lo que revierte su efecto, y se registra uno nuevo.
 */
class MovimientoAlmacenController extends CrudController
{
    protected string $modelo = MovimientoAlmacen::class;

    protected bool $conAprobacion = true;

    protected array $con = ['almacen', 'insumo', 'personal'];

    protected array $buscar = ['referencia', 'observacion'];

    protected array $filtros = ['almacen_id', 'insumo_id', 'tipo', 'compra_id', 'personal_id', 'motivo'];

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
            'motivo' => ['nullable', Rule::in(array_keys(config('sofia.motivos_movimiento')))],
            // Quien retira o devuelve: obligatorio en préstamos y devoluciones de herramientas.
            'personal_id' => [Rule::requiredIf(in_array(request('motivo'), ['PRESTAMO', 'DEVOLUCION', 'CONSUMO'], true)), 'nullable', $this->existe('personal')],
            'visita_id' => ['nullable', $this->existe('visitas_mantenimiento')],
            'referencia' => ['nullable', 'string', 'max:255'],
            'observacion' => ['nullable', 'string'],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate($this->reglas(null));
        $this->exigirTipoDeAlmacen($datos);
        $this->exigirCoherenciaDeMotivo($datos);
        $movimiento = app(InventarioService::class)->registrar($datos);

        return $this->responder($movimiento->refresh())->setStatusCode(201);
    }

    /** Un bien con tipo de almacén definido (herramientas, repuestos...) solo se mueve en un almacén de ese tipo. */
    private function exigirTipoDeAlmacen(array $datos): void
    {
        $tipoBien = Insumo::find($datos['insumo_id'])?->tipo_almacen;
        $almacen = Almacen::find($datos['almacen_id']);

        if ($tipoBien && $almacen && $almacen->tipo_almacen !== $tipoBien) {
            $nombre = config("sofia.tipos_almacen.{$tipoBien}", $tipoBien);
            throw ValidationException::withMessages(['almacen_id' => "Este bien se guarda en un \"{$nombre}\"; el almacén elegido es de otro tipo."]);
        }
    }

    private function exigirCoherenciaDeMotivo(array $datos): void
    {
        $motivo = $datos['motivo'] ?? null;
        $esperado = ['PRESTAMO' => 'SALIDA', 'CONSUMO' => 'SALIDA', 'DEVOLUCION' => 'INGRESO', 'INICIAL' => 'INGRESO', 'COMPRA' => 'INGRESO'][$motivo] ?? null;

        if ($esperado && $esperado !== $datos['tipo']) {
            throw ValidationException::withMessages(['motivo' => 'El motivo elegido no corresponde a un '.strtolower($datos['tipo']).'.']);
        }
    }

    /** Herramientas que siguen fuera del almacén y quién las tiene. */
    public function herramientasEnPoder(InventarioService $inventario): JsonResponse
    {
        return response()->json(['data' => $inventario->herramientasEnPoder()]);
    }

    /** Dónde hay existencias de un bien. */
    public function existencias(string $insumoId, InventarioService $inventario): JsonResponse
    {
        return response()->json(['data' => $inventario->existencias((int) $insumoId)]);
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
