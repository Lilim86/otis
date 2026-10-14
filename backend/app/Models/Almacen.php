<?php

namespace App\Models;

class Almacen extends Auditable
{
    protected $table = 'almacenes';

    protected $fillable = ['codigo', 'nombre', 'tipo_almacen', 'regional_id'];

    public function regional()
    {
        return $this->belongsTo(Departamento::class, 'regional_id');
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoAlmacen::class, 'almacen_id');
    }
}
