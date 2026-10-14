<?php

namespace App\Http\Controllers\Api;

use App\Models\Ascensor;
use Illuminate\Database\Eloquent\Model;

class AscensorController extends CrudController
{
    protected string $modelo = Ascensor::class;

    protected array $con = ['zona'];

    protected array $buscar = ['codigo', 'cliente_edificio', 'modelo'];

    protected array $filtros = ['zona_id'];

    protected array $orden = [['codigo', 'asc']];

    protected array $dependientes = ['emergencias' => 'emergencias'];

    protected array $padres = ['zona' => 'zona'];

    protected function reglas(?Model $registro): array
    {
        return [
            'codigo' => ['required', 'string', 'max:255', $this->unico('ascensores', 'codigo', $registro)],
            'cliente_edificio' => ['required', 'string', 'max:255'],
            'zona_id' => ['required', $this->existe('zonas')],
            'modelo' => ['nullable', 'string', 'max:255'],
        ];
    }
}
