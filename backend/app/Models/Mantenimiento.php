<?php

namespace App\Models;

class Mantenimiento extends Auditable
{
    protected $table = 'mantenimientos';

    protected $fillable = [
        'codigo_ascensor',
        'zona_id',
        'tipo',
        'detalles',
        'fecha_programada',
        'estado',
    ];

    public function zona()
    {
        return $this->belongsTo(Zona::class, 'zona_id');
    }
}
