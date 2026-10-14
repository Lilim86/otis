<?php

namespace App\Http\Controllers\Api;

use App\Models\CuentaBancaria;
use App\Models\TraspasoCuenta;
use Illuminate\Database\Eloquent\Model;

/** Traspasos entre cuentas bancarias de la empresa. */
class TraspasoCuentaController extends CrudController
{
    protected string $modelo = TraspasoCuenta::class;

    protected bool $conAprobacion = true;

    protected array $estadosSinEdicion = ['APR', 'ANL'];

    protected array $con = ['cuentaOrigen.banco', 'cuentaDestino.banco'];

    protected array $buscar = ['referencia', 'concepto'];

    protected array $filtros = ['cuenta_origen_id', 'cuenta_destino_id'];

    protected array $fechas = ['fecha'];

    protected array $orden = [['fecha', 'desc']];

    protected array $padres = ['cuentaOrigen' => 'cuenta de origen', 'cuentaDestino' => 'cuenta de destino'];

    protected function reglas(?Model $registro): array
    {
        $origen = request()->input('cuenta_origen_id');

        return [
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'cuenta_origen_id' => ['required', $this->existe('cuentas_bancarias')],
            'cuenta_destino_id' => ['required', 'different:cuenta_origen_id', $this->existe('cuentas_bancarias'), function ($atributo, $valor, $fallar) use ($origen) {
                // Sin tipo de cambio de por medio: ambas cuentas deben ser de la misma moneda.
                $monedas = CuentaBancaria::whereIn('id', array_filter([$origen, $valor]))->pluck('moneda', 'id');
                if ($monedas->count() === 2 && $monedas->unique()->count() > 1) {
                    $fallar('Las dos cuentas deben ser de la misma moneda.');
                }
            }],
            'monto' => ['required', 'numeric', 'gt:0'],
            'referencia' => ['nullable', 'string', 'max:60'],
            'concepto' => ['nullable', 'string', 'max:255'],
        ];
    }
}
