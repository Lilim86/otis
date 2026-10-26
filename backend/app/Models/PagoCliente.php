<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;

class PagoCliente extends Auditable
{
    use TieneAprobacion;

    protected $table = 'pagos_clientes';

    protected $fillable = [
        'empresa_id', 'contrato_id', 'cuota_id', 'user_id', 'monto_bs', 'fecha_pago', 'nro_transaccion',
        'banco_origen', 'cuenta_destino', 'observacion', 'estado',
    ];

    protected function casts(): array
    {
        return ['fecha_pago' => 'date:Y-m-d'];
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function contrato()
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function cuota()
    {
        return $this->belongsTo(CuotaContrato::class, 'cuota_id');
    }

    public function cobranza()
    {
        return $this->belongsTo(Cobranza::class, 'cobranza_id');
    }
}
