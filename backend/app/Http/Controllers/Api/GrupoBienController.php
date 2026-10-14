<?php

namespace App\Http\Controllers\Api;

use App\Models\GrupoBien;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class GrupoBienController extends CrudController
{
    protected string $modelo = GrupoBien::class;

    protected array $con = ['padre'];

    protected array $buscar = ['codigo', 'nombre'];

    protected array $filtros = ['nivel', 'padre_id'];

    protected array $orden = [['codigo', 'asc']];

    protected array $dependientes = ['hijos' => 'subgrupos'];

    protected array $padres = ['padre' => 'grupo padre'];

    protected function reglas(?Model $registro): array
    {
        return [
            'codigo' => ['required', 'string', 'max:10', $this->unico('grupos_bienes', 'codigo', $registro)],
            'nombre' => ['required', 'string', 'max:255'],
            'nivel' => ['required', 'integer', 'between:1,3'],
            // Un grupo (nivel 1) no tiene padre; subgrupos 1 y 2 cuelgan del nivel inmediato superior.
            'padre_id' => [
                'nullable', 'required_unless:nivel,1', 'prohibited_if:nivel,1',
                Rule::exists('grupos_bienes', 'id')->whereNull('deleted_at')->where('nivel', max((int) request('nivel') - 1, 1)),
            ],
        ];
    }
}
