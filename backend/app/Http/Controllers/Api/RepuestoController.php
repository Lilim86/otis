<?php

namespace App\Http\Controllers\Api;

use App\Models\Repuesto;
use Illuminate\Database\Eloquent\Model;

class RepuestoController extends CrudController
{
    protected string $modelo = Repuesto::class;

    protected array $buscar = ['codigo_parte', 'nombre', 'ubicacion_almacen'];

    protected function reglas(?Model $registro): array
    {
        return [
            'codigo_parte' => ['required', 'string', 'max:255', $this->unico('repuestos', 'codigo_parte', $registro)],
            'nombre' => ['required', 'string', 'max:255'],
            'stock' => ['required', 'integer', 'min:0'],
            'precio' => ['required', 'numeric', 'min:0'],
            'ubicacion_almacen' => ['required', 'string'],
        ];
    }
}
