<?php

namespace App\Http\Controllers\Api;

use App\Models\IngresoTesoreria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Recibos oficiales de ingresos y traspasos de ingresos (cobros por extractos). */
class IngresoTesoreriaController extends CrudController
{
    protected string $modelo = IngresoTesoreria::class;

    protected bool $conAprobacion = true;

    protected array $estadosSinEdicion = ['APR', 'ANL'];

    protected array $con = ['cuentaBancaria.banco', 'cobranza'];

    protected array $buscar = ['nro_recibo', 'concepto'];

    protected array $filtros = ['tipo', 'cuenta_bancaria_id', 'cobranza_id'];

    protected array $fechas = ['fecha'];

    protected array $orden = [['fecha', 'desc']];

    protected array $padres = ['cuentaBancaria' => 'cuenta bancaria'];

    protected function reglas(?Model $registro): array
    {
        return [
            'tipo' => ['required', Rule::in(array_keys(config('sofia.tipos_ingreso')))],
            'nro_recibo' => ['required', 'string', 'max:40', $this->unico('ingresos_tesoreria', 'nro_recibo', $registro)],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'cuenta_bancaria_id' => ['required', $this->existe('cuentas_bancarias')],
            'cobranza_id' => ['nullable', $this->existe('cobranzas')],
            'monto_bs' => ['required_without:monto_usd', 'nullable', 'numeric', 'min:0'],
            'monto_usd' => ['required_without:monto_bs', 'nullable', 'numeric', 'min:0'],
            'concepto' => ['nullable', 'string', 'max:255'],
        ];
    }
}
