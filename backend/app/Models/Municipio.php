<?php

namespace App\Models;

class Municipio extends Auditable
{
    protected $table = 'municipios';

    protected $fillable = ['nombre', 'provincia_id'];

    public function provincia()
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function zonas()
    {
        return $this->hasMany(Zona::class, 'municipio_id');
    }
}
