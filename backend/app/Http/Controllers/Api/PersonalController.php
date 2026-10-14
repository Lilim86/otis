<?php

namespace App\Http\Controllers\Api;

use App\Models\Personal;
use Illuminate\Database\Eloquent\Model;

class PersonalController extends CrudController
{
    protected string $modelo = Personal::class;

    protected array $con = ['cargo', 'profesion'];

    protected array $buscar = ['nombres', 'apellidos', 'ci'];

    protected array $filtros = ['cargo_id', 'profesion_id'];

    protected array $orden = [['apellidos', 'asc']];

    protected array $padres = ['cargo' => 'cargo', 'profesion' => 'profesión'];

    protected function reglas(?Model $registro): array
    {
        return [
            'cargo_id' => ['required', $this->existe('cargos')],
            'profesion_id' => ['required', $this->existe('profesiones')],
            'ci' => ['required', 'string', 'max:50', $this->unico('personal', 'ci', $registro)],
            'nombres' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
        ];
    }
}
