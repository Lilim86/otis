<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;

class FacturaProveedor extends Auditable
{
    use TieneAprobacion;

    protected $table = 'facturas_proveedor';

    protected $fillable = ['compra_id', 'fecha_factura', 'nro_factura', 'importe_bs', 'concepto'];

    protected function casts(): array
    {
        return ['fecha_factura' => 'date:Y-m-d'];
    }

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function ordenesCancelacion()
    {
        return $this->hasMany(OrdenCancelacion::class, 'factura_proveedor_id');
    }
}
