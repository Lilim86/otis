<?php

namespace App\Models;

class ClasificadorTecnico extends Auditable
{
    protected $table = 'clasificadores_tecnicos';

    protected $fillable = ['nombre', 'descripcion'];
}
