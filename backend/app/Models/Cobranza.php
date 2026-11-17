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
        'tipo_cobro',
        'metodo_pago',
        'nro_cheque',
        'banco_cheque',
    ];

    protected function casts(): array
    {
        return ['enviado_en' => 'datetime', 'revisado_en' => 'datetime'];
    }

    /** Quien de Tesorería aprobó u observó el cobro. */
    public function revisor()
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

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
