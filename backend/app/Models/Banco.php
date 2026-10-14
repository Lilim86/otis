<?php

namespace App\Models;

class Banco extends Auditable
{
    protected $table = 'bancos';

    protected $fillable = ['nombre', 'sigla', 'estado'];

    public function cuentasBancarias()
    {
        return $this->hasMany(CuentaBancaria::class, 'banco_id');
    }
}
