<?php

namespace App\Http\Controllers\Api;

use App\Models\Provincia;
use Illuminate\Database\Eloquent\Model;

class ProvinciaController extends CrudController
{
    protected string $modelo = Provincia::class;

    protected array $con = ['departamento'];

    protected array $buscar = ['nombre'];

    protected array $filtros = ['departamento_id'];

    protected array $orden = [['nombre', 'asc']];

    protected array $dependientes = ['municipios' => 'municipios'];

    protected array $padres = ['departamento' => 'departamento'];

    protected function reglas(?Model $registro): array
    {
        return [
            'departamento_id' => ['required', $this->existe('departamentos')],
            'nombre' => ['required', 'string', 'max:255', $this->unico('provincias', 'nombre', $registro)],
        ];
    }
}
