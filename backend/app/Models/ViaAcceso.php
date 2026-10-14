<?php

namespace App\Models;

class ViaAcceso extends Auditable
{
    protected $table = 'via_accesos';

    protected $fillable = [
        'zona_id',
        'nombre',
        'estado',
    ];

    public function zona()
    {
        return $this->belongsTo(Zona::class, 'zona_id');
    }
}
