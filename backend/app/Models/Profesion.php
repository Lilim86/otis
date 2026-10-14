<?php

namespace App\Models;

class Profesion extends Auditable
{
    protected $table = 'profesiones';

    protected $fillable = ['nombre', 'estado'];

    public function personal()
    {
        return $this->hasMany(Personal::class, 'profesion_id');
    }
}
