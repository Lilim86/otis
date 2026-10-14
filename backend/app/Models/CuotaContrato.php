<?php

namespace App\Models;

class CuotaContrato extends Auditable
{
    protected $table = 'cuotas_contrato';

    protected $fillable = ['contrato_id', 'nro_cuota', 'fecha_cuota', 'estado_cuota', 'monto_bs', 'concepto', 'certificado', 'fecha_conformidad'];

    protected function casts(): array
    {
        return ['fecha_cuota' => 'date:Y-m-d', 'fecha_conformidad' => 'date:Y-m-d'];
    }

    public function contrato()
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function cobranzas()
    {
        return $this->hasMany(Cobranza::class, 'cuota_id');
    }
}
