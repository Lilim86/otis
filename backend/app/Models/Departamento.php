<?php

namespace App\Models;

class Departamento extends Auditable
{
    protected $table = 'departamentos';

    protected $fillable = ['nombre', 'pais_id'];

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }

    public function provincias()
    {
        return $this->hasMany(Provincia::class, 'departamento_id');
    }
}
