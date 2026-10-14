<?php

namespace App\Models;

class Provincia extends Auditable
{
    protected $table = 'provincias';

    protected $fillable = [
        'departamento_id',
        'nombre',
        'estado',
    ];

    // Relación: Una provincia pertenece a un departamento
    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    public function municipios()
    {
        return $this->hasMany(Municipio::class, 'provincia_id');
    }
}
