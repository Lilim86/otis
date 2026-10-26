<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnvioComprobante extends Model
{
    protected $table = 'envios_comprobante';

    public $timestamps = false;

    protected $guarded = [];
}
