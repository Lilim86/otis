<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;

class ContratoTecnico extends Auditable
{
    use TieneAprobacion;

    protected $table = 'contratos_tecnicos';

    protected $fillable = ['personal_id', 'fecha_inicio', 'fecha_fin', 'monto_mensual_bs', 'objeto'];

    protected function casts(): array
    {
        return ['fecha_inicio' => 'date:Y-m-d', 'fecha_fin' => 'date:Y-m-d'];
    }

    public function personal()
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }
}
