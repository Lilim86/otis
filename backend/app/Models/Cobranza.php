<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;

class Cobranza extends Auditable
{
    use TieneAprobacion;

    protected $table = 'cobranzas';

    protected $fillable = [
        'contrato_id',
        'concepto',
        'monto_facturado',
        'fecha_cobro',
        'nro_factura',
        'estado',
        'cuota_id',
        'cuenta_bancaria_id',
        'cobrador_id',
        'comprobante_deposito',
        'nro_recibo',
        'cobrado_bs',
        'cobrado_usd',
        'fecha_facturacion',
        'nit_facturado',
        'factura_a_nombre',
    ];

    public function contrato()
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function cuota()
    {
        return $this->belongsTo(CuotaContrato::class, 'cuota_id');
    }

    public function cuentaBancaria()
    {
        return $this->belongsTo(CuentaBancaria::class, 'cuenta_bancaria_id');
    }

    public function cobrador()
    {
        return $this->belongsTo(Persona::class, 'cobrador_id');
    }
}
