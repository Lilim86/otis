<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;

class Planilla extends Auditable
{
    use TieneAprobacion;

    protected $table = 'planillas';

    protected $fillable = ['periodo', 'tipo', 'regional_id', 'total_haberes_bs', 'total_descuentos_bs', 'total_liquido_bs', 'observacion'];

    public function regional()
    {
        return $this->belongsTo(Departamento::class, 'regional_id');
    }

    public function detalles()
    {
        return $this->hasMany(PlanillaDetalle::class, 'planilla_id');
    }
}
