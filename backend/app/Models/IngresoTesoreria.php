<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;

class IngresoTesoreria extends Auditable
{
    use TieneAprobacion;

    protected $table = 'ingresos_tesoreria';

    protected $fillable = ['tipo', 'nro_recibo', 'fecha', 'cuenta_bancaria_id', 'cobranza_id', 'monto_bs', 'monto_usd', 'concepto'];

    protected function casts(): array
    {
        return ['fecha' => 'date:Y-m-d'];
    }

    public function cuentaBancaria()
    {
        return $this->belongsTo(CuentaBancaria::class, 'cuenta_bancaria_id');
    }

    public function cobranza()
    {
        return $this->belongsTo(Cobranza::class, 'cobranza_id');
    }
}
