<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;

class Equipo extends Auditable
{
    use TieneAprobacion;

    protected $table = 'equipos';

    protected $fillable = [
        'codigo',
        'descripcion',
        'edificio_id',
        'industria',
        'marca',
        'modelo',
        'paradas',
        'estado',
        'grupo_id',
        'subgrupo1_id',
        'subgrupo2_id',
        'caracteristicas',
        'unidad_medida',
        'codigo_referencia',
        'rotacion',
        'precio_compra_fob_bs',
        'precio_compra_fob_usd',
        'precio_venta_base_bs',
        'precio_venta_base_usd',
        'precio_venta_cliente_bs',
        'precio_venta_cliente_usd',
        'horas_servicio',
    ];

    // Un equipo pertenece a un edificio
    public function edificio()
    {
        return $this->belongsTo(Edificio::class, 'edificio_id');
    }

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

    public function insumosBasicos()
    {
        return $this->belongsToMany(Insumo::class, 'equipo_insumos')->withPivot('cantidad')->withTimestamps();
    }
}
