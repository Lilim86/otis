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

    /** Total ya cobrado de la cuota (sin contar cobranzas anuladas). */
    public function cobrado(): float
    {
        return (float) $this->cobranzas()->where('estado_registro', '!=', 'ANL')->sum('cobrado_bs');
    }

    /** Si lo cobrado cubre el monto, la cuota pasa a COBRADA. */
    public function actualizarPorCobros(): void
    {
        if ($this->estado_cuota !== 'COBRADA' && $this->cobrado() >= (float) $this->monto_bs && $this->cobrado() > 0) {
            $this->update(['estado_cuota' => 'COBRADA']);
        }
    }
}
