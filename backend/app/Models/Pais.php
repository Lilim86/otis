<?php

namespace App\Models;

class Pais extends Auditable
{
    // ¡Esta línea obliga a Laravel a buscar la tabla en plural!
    protected $table = 'paises';

    protected $fillable = [
        'nombre',
        'codigo_iso',
        'estado',
    ];

    public function departamentos()
    {
        return $this->hasMany(Departamento::class, 'pais_id');
    }
}
