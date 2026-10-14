<?php

namespace App\Http\Controllers\Api;

use App\Models\Banco;
use Illuminate\Database\Eloquent\Model;

class BancoController extends CrudController
{
    protected string $modelo = Banco::class;

    protected array $buscar = ['nombre', 'sigla'];

    protected array $orden = [['nombre', 'asc']];

    protected array $dependientes = ['cuentasBancarias' => 'cuentas bancarias'];

    protected function reglas(?Model $registro): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255', $this->unico('bancos', 'nombre', $registro)],
            'sigla' => ['nullable', 'string', 'max:50'],
        ];
    }
}
