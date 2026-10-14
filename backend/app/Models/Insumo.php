<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;

class Insumo extends Auditable
{
    use TieneAprobacion;

    protected $table = 'insumos';

    protected $fillable = [
        'codigo',
        'descripcion',
        'tipo',
        'stock_actual',
        'estado',
        'grupo_id',
        'subgrupo1_id',
        'subgrupo2_id',
        'caracteristicas',
        'unidad_medida',
        'industria',
        'marca',
        'modelo',
        'codigo_referencia',
        'rotacion',
        'stock_minimo',
        'precio_compra_bs',
        'precio_compra_usd',
        'precio_venta_base_bs',
        'precio_venta_base_usd',
        'precio_venta_cliente_bs',
        'precio_venta_cliente_usd',
        'tipo_almacen',
        'codigo_sin',
        'descripcion_sin',
    ];

    public function grupo()
    {
        return $this->belongsTo(GrupoBien::class, 'grupo_id');
    }

    public function subgrupo1()
    {
        return $this->belongsTo(GrupoBien::class, 'subgrupo1_id');
    }

    public function subgrupo2()
    {
        return $this->belongsTo(GrupoBien::class, 'subgrupo2_id');
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoAlmacen::class, 'insumo_id');
    }
}
