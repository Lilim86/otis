<?php

namespace App\Models;

class Cargo extends Auditable
{
    protected $table = 'cargos';

    protected $fillable = ['nombre', 'estado'];

    public function personal()
    {
        return $this->hasMany(Personal::class, 'cargo_id');
    }
}
