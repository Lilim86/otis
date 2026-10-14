<?php

namespace App\Http\Controllers\Api;

use App\Models\ViaAcceso;
use Illuminate\Database\Eloquent\Model;

class ViaAccesoController extends CrudController
{
    protected string $modelo = ViaAcceso::class;

    protected array $con = ['zona'];

    protected array $buscar = ['nombre'];

    protected array $filtros = ['zona_id'];

    protected array $orden = [['nombre', 'asc']];

    protected array $padres = ['zona' => 'zona'];

    protected function reglas(?Model $registro): array
    {
        return [
            'zona_id' => ['required', $this->existe('zonas')],
            'nombre' => ['required', 'string', 'max:255', $this->unico('via_accesos', 'nombre', $registro)],
        ];
    }
}
