<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;

class MovimientoAlmacen extends Auditable
{
    use TieneAprobacion;

    protected $table = 'movimientos_almacen';

    protected $fillable = ['almacen_id', 'insumo_id', 'tipo', 'cantidad', 'costo_unitario_bs', 'fecha', 'referencia', 'compra_id', 'observacion'];

    protected function casts(): array
    {
        return ['fecha' => 'date:Y-m-d'];
    }

    public function almacen()
    {
        return $this->belongsTo(Almacen::class, 'almacen_id');
    }

    public function insumo()
    {
        return $this->belongsTo(Insumo::class, 'insumo_id');
    }

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }
}
