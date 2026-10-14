<?php

namespace App\Http\Controllers\Api;

use App\Models\ClasificadorTecnico;
use Illuminate\Database\Eloquent\Model;

class ClasificadorTecnicoController extends CrudController
{
    protected string $modelo = ClasificadorTecnico::class;

    protected array $buscar = ['nombre', 'descripcion'];

    protected array $orden = [['nombre', 'asc']];

    protected function reglas(?Model $registro): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255', $this->unico('clasificadores_tecnicos', 'nombre', $registro)],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }
}
