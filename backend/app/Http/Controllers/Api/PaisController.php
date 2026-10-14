<?php

namespace App\Http\Controllers\Api;

use App\Models\Pais;
use Illuminate\Database\Eloquent\Model;

class PaisController extends CrudController
{
    protected string $modelo = Pais::class;

    protected array $buscar = ['nombre', 'codigo_iso'];

    protected array $orden = [['nombre', 'asc']];

    protected array $dependientes = ['departamentos' => 'departamentos'];

    protected function reglas(?Model $registro): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255', $this->unico('paises', 'nombre', $registro)],
            'codigo_iso' => ['nullable', 'string', 'max:10'],
        ];
    }
}
