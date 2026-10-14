<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;

class FondoRendir extends Auditable
{
    use TieneAprobacion;

    protected $table = 'fondos_rendir';

    protected $fillable = ['tipo', 'codigo', 'responsable_id', 'regional_id', 'fecha', 'monto_asignado_bs', 'concepto'];

    protected function casts(): array
    {
        return ['fecha' => 'date:Y-m-d'];
    }

    public function responsable()
    {
        return $this->belongsTo(Persona::class, 'responsable_id');
    }

    public function regional()
    {
        return $this->belongsTo(Departamento::class, 'regional_id');
    }

    public function rendiciones()
    {
        return $this->hasMany(RendicionFondo::class, 'fondo_id');
    }
}
