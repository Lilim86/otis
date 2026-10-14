<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;

class OrdenCancelacion extends Auditable
{
    use TieneAprobacion;

    protected $table = 'ordenes_cancelacion';

    protected $fillable = ['compra_id', 'factura_proveedor_id', 'nro_oc', 'fecha_pago', 'cuenta_origen_id', 'comprobante_deposito', 'pagado_bs', 'pagado_usd', 'concepto'];

    protected function casts(): array
    {
        return ['fecha_pago' => 'date:Y-m-d'];
    }

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function factura()
    {
        return $this->belongsTo(FacturaProveedor::class, 'factura_proveedor_id');
    }

    public function cuentaOrigen()
    {
        return $this->belongsTo(CuentaBancaria::class, 'cuenta_origen_id');
    }
}
