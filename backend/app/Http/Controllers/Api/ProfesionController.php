<?php

namespace App\Http\Controllers\Api;

use App\Models\Profesion;
use Illuminate\Database\Eloquent\Model;

class ProfesionController extends CrudController
{
    protected string $modelo = Profesion::class;

    protected array $buscar = ['nombre'];

    protected array $orden = [['nombre', 'asc']];

    protected array $dependientes = ['personal' => 'personal'];

    protected function reglas(?Model $registro): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255', $this->unico('profesiones', 'nombre', $registro)],
        ];
    }
}
