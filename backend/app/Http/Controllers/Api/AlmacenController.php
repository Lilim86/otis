<?php

namespace App\Http\Controllers\Api;

use App\Models\Almacen;
use App\Services\InventarioService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class AlmacenController extends CrudController
{
    protected string $modelo = Almacen::class;

    protected array $con = ['regional'];

    protected array $buscar = ['codigo', 'nombre'];

    protected array $filtros = ['tipo_almacen', 'regional_id'];

    protected array $orden = [['codigo', 'asc']];

    protected array $dependientes = ['movimientos' => 'movimientos de inventario'];

    protected array $padres = ['regional' => 'regional'];

    protected function reglas(?Model $registro): array
    {
        return [
            'codigo' => ['required', 'string', 'max:20', $this->unico('almacenes', 'codigo', $registro)],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo_almacen' => ['required', Rule::in(array_keys(config('sofia.tipos_almacen')))],
            'regional_id' => ['nullable', $this->existe('departamentos')],
        ];
    }

    /** Inventario del almacén por bien: ingresado, salido, saldo y valor (REP-0069). */
    public function inventario(string $id, InventarioService $servicio): JsonResponse
    {
        $almacen = $this->buscarRegistro($id);

        return response()->json(['data' => [
            'almacen' => $almacen->only(['id', 'codigo', 'nombre']),
            'bienes' => $servicio->inventario($almacen->id),
        ]]);
    }
}
