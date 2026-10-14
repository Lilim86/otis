<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;

class RendicionFondo extends Auditable
{
    use TieneAprobacion;

    protected $table = 'rendiciones_fondo';

    protected $fillable = ['fondo_id', 'fecha', 'nro_comprobante', 'concepto', 'monto_bs'];

    protected function casts(): array
    {
        return ['fecha' => 'date:Y-m-d'];
    }

    public function fondo()
    {
        return $this->belongsTo(FondoRendir::class, 'fondo_id');
    }
}
