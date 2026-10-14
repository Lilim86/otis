<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;

class Compra extends Auditable
{
    use TieneAprobacion;

    protected $table = 'compras';

    protected $fillable = ['codigo_compra', 'proveedor_id', 'proveedor_empresa_id', 'regional_id', 'unidad_oficina', 'almacen_id', 'fecha_compra', 'total_bs', 'total_usd', 'observacion'];

    protected function casts(): array
    {
        return ['fecha_compra' => 'date:Y-m-d'];
    }

    public function proveedor()
    {
        return $this->belongsTo(Persona::class, 'proveedor_id');
    }

    public function proveedorEmpresa()
    {
        return $this->belongsTo(Empresa::class, 'proveedor_empresa_id');
    }

    public function regional()
    {
        return $this->belongsTo(Departamento::class, 'regional_id');
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'almacen_id');
    }

    public function detalles()
    {
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }

    public function facturas()
    {
        return $this->hasMany(FacturaProveedor::class, 'compra_id');
    }

    public function ordenesCancelacion()
    {
        return $this->hasMany(OrdenCancelacion::class, 'compra_id');
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoAlmacen::class, 'compra_id');
    }
}
