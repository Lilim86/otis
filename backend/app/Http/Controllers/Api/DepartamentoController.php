<?php

namespace App\Http\Controllers\Api;

use App\Models\Departamento;
use Illuminate\Database\Eloquent\Model;

class DepartamentoController extends CrudController
{
    protected string $modelo = Departamento::class;

    protected array $con = ['pais'];

    protected array $buscar = ['nombre'];

    protected array $filtros = ['pais_id'];

    protected array $orden = [['nombre', 'asc']];

    protected array $dependientes = ['provincias' => 'provincias'];

    protected array $padres = ['pais' => 'país'];

    protected function reglas(?Model $registro): array
    {
        return [
            'pais_id' => ['required', $this->existe('paises')],
            'nombre' => ['required', 'string', 'max:255', $this->unico('departamentos', 'nombre', $registro)],
        ];
    }
}
