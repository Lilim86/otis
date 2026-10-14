<?php

namespace App\Http\Controllers\Api;

use App\Models\Municipio;
use Illuminate\Database\Eloquent\Model;

class MunicipioController extends CrudController
{
    protected string $modelo = Municipio::class;

    protected array $con = ['provincia'];

    protected array $buscar = ['nombre'];

    protected array $filtros = ['provincia_id'];

    protected array $orden = [['nombre', 'asc']];

    protected array $dependientes = ['zonas' => 'zonas'];

    protected array $padres = ['provincia' => 'provincia'];

    protected function reglas(?Model $registro): array
    {
        return [
            'provincia_id' => ['required', $this->existe('provincias')],
            'nombre' => ['required', 'string', 'max:255', $this->unico('municipios', 'nombre', $registro)],
        ];
    }
}
