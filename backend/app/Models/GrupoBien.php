<?php

namespace App\Models;

class GrupoBien extends Auditable
{
    protected $table = 'grupos_bienes';

    protected $fillable = ['codigo', 'nombre', 'nivel', 'padre_id'];

    public function padre()
    {
        return $this->belongsTo(self::class, 'padre_id');
    }

    public function hijos()
    {
        return $this->hasMany(self::class, 'padre_id');
    }
}
