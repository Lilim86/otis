<?php

namespace App\Models;

class Personal extends Auditable
{
    protected $table = 'personal';

    protected $fillable = ['cargo_id', 'profesion_id', 'ci', 'nombres', 'apellidos', 'estado'];

    public function cargo()
    {
        return $this->belongsTo(Cargo::class, 'cargo_id');
    }

    public function profesion()
    {
        return $this->belongsTo(Profesion::class, 'profesion_id');
    }
}
