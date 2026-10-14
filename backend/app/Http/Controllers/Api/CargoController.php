<?php

namespace App\Http\Controllers\Api;

use App\Models\Cargo;
use Illuminate\Database\Eloquent\Model;

class CargoController extends CrudController
{
    protected string $modelo = Cargo::class;

    protected array $buscar = ['nombre'];

    protected array $orden = [['nombre', 'asc']];

    protected array $dependientes = ['personal' => 'personal'];

    protected function reglas(?Model $registro): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255', $this->unico('cargos', 'nombre', $registro)],
        ];
    }
}
