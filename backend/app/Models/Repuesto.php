<?php

namespace App\Models;

class Repuesto extends Auditable
{
    protected $fillable = [
        'codigo_parte',
        'nombre',
        'stock',
        'precio',
        'ubicacion_almacen',
    ];
}
