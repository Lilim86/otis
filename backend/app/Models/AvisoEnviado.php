<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvisoEnviado extends Model
{
    protected $table = 'avisos_enviados';

    public $timestamps = false;

    protected $guarded = [];
}
