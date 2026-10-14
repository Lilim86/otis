<?php

namespace App\Models;

class Emergencia extends Auditable
{
    protected $table = 'emergencias';

    protected $fillable = [
        'ascensor_id',
        'gravedad',
        'descripcion_fallo',
        'situacion',
    ];

    public function ascensor()
    {
        return $this->belongsTo(Ascensor::class, 'ascensor_id');
    }
}
