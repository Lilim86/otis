<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;

class Contrato extends Auditable
{
    use TieneAprobacion;

    protected $table = 'contratos';

    protected $fillable = [
        'empresa_id',
        'edificio_id',
        'fecha_inicio',
        'fecha_fin',
        'monto_total',
        'estado',
        'codigo_contrato',
        'regional_id',
        'unidad_oficina',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function edificio()
    {
        return $this->belongsTo(Edificio::class, 'edificio_id');
    }

    public function cobranzas()
    {
        return $this->hasMany(Cobranza::class, 'contrato_id');
    }

    public function regional()
    {
        return $this->belongsTo(Departamento::class, 'regional_id');
    }

    public function cuotas()
    {
        return $this->hasMany(CuotaContrato::class, 'contrato_id');
    }

    public function visitas()
    {
        return $this->hasMany(VisitaMantenimiento::class, 'contrato_id');
    }
}
