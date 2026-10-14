<?php

namespace App\Models;

class Zona extends Auditable
{
    protected $table = 'zonas';

    protected $fillable = [
        'municipio_id',
        'nombre',
        'estado',
    ];

    public function municipio()
    {
        return $this->belongsTo(Municipio::class, 'municipio_id');
    }

    public function viasAcceso()
    {
        return $this->hasMany(ViaAcceso::class, 'zona_id');
    }

    public function ascensores()
    {
        return $this->hasMany(Ascensor::class, 'zona_id');
    }

    public function mantenimientos()
    {
        return $this->hasMany(Mantenimiento::class, 'zona_id');
    }
}
