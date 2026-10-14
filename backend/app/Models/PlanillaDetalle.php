<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanillaDetalle extends Model
{
    protected $table = 'planilla_detalles';

    protected $fillable = ['planilla_id', 'personal_id', 'haber_basico_bs', 'descuentos_bs', 'liquido_bs'];

    public function personal()
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }
}
