<?php

namespace App\Models;

class Ascensor extends Auditable
{
    protected $table = 'ascensores';

    protected $fillable = ['codigo', 'cliente_edificio', 'zona_id', 'modelo'];

    public function zona()
    {
        return $this->belongsTo(Zona::class, 'zona_id');
    }

    public function emergencias()
    {
        return $this->hasMany(Emergencia::class, 'ascensor_id');
    }
}
