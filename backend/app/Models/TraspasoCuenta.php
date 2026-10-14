<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;

class TraspasoCuenta extends Auditable
{
    use TieneAprobacion;

    protected $table = 'traspasos_cuentas';

    protected $fillable = ['fecha', 'cuenta_origen_id', 'cuenta_destino_id', 'monto', 'referencia', 'concepto'];

    protected function casts(): array
    {
        return ['fecha' => 'date:Y-m-d'];
    }

    public function cuentaOrigen()
    {
        return $this->belongsTo(CuentaBancaria::class, 'cuenta_origen_id');
    }

    public function cuentaDestino()
    {
        return $this->belongsTo(CuentaBancaria::class, 'cuenta_destino_id');
    }
}
