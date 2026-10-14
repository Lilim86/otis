<?php

namespace App\Models;

class OrdenMantenimiento extends Auditable
{
    protected $fillable = [
        'codigo_orden',
        'equipo_ascensor',
        'fecha_programada',
        'situacion',
        'descripcion_trabajo',
        'repuesto_id',
        'cantidad_utilizada',
    ];

    // Esta función conecta la orden con el repuesto utilizado
    public function repuesto()
    {
        return $this->belongsTo(Repuesto::class);
    }
}
