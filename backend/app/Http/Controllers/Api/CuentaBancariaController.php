<?php

namespace App\Http\Controllers\Api;

use App\Models\CuentaBancaria;
use Illuminate\Database\Eloquent\Model;

class CuentaBancariaController extends CrudController
{
    protected string $modelo = CuentaBancaria::class;

    protected array $con = ['banco'];

    protected array $buscar = ['numero_cuenta'];

    protected array $filtros = ['banco_id', 'moneda'];

    protected array $orden = [['banco_id', 'asc']];

    protected array $padres = ['banco' => 'banco'];

    protected function reglas(?Model $registro): array
    {
        return [
            'banco_id' => ['required', $this->existe('bancos')],
            'numero_cuenta' => ['required', 'string', 'max:255', $this->unico('cuentas_bancarias', 'numero_cuenta', $registro)],
            'moneda' => ['required', 'in:BOB,USD'],
        ];
    }
}
