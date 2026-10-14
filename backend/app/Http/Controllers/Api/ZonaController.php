<?php

namespace App\Http\Controllers\Api;

use App\Models\Zona;
use Illuminate\Database\Eloquent\Model;

class ZonaController extends CrudController
{
    protected string $modelo = Zona::class;

    protected array $con = ['municipio'];

    protected array $buscar = ['nombre'];

    protected array $filtros = ['municipio_id'];

    protected array $orden = [['nombre', 'asc']];

    protected array $dependientes = ['viasAcceso' => 'vías de acceso', 'ascensores' => 'ascensores', 'mantenimientos' => 'mantenimientos'];

    protected array $padres = ['municipio' => 'municipio'];

    protected function reglas(?Model $registro): array
    {
        return [
            'municipio_id' => ['required', $this->existe('municipios')],
            'nombre' => ['required', 'string', 'max:255', $this->unico('zonas', 'nombre', $registro)],
        ];
    }
}
