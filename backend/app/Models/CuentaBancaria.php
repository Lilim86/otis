<?php

namespace App\Models;

class CuentaBancaria extends Auditable
{
    protected $table = 'cuentas_bancarias';

    protected $fillable = ['banco_id', 'numero_cuenta', 'moneda', 'estado'];

    public function banco()
    {
        return $this->belongsTo(Banco::class, 'banco_id');
    }
}
