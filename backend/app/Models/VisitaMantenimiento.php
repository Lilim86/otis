<?php

namespace App\Models;

class VisitaMantenimiento extends Auditable
{
    protected $table = 'visitas_mantenimiento';

    protected $fillable = [
        'contrato_id', 'nro_visita', 'tipo', 'fecha_programada', 'fecha_realizada', 'situacion', 'tecnico_id',
        'descripcion', 'informe', 'calificacion', 'comentario_cliente', 'solicitada_por', 'estado',
    ];

    protected function casts(): array
    {
        return ['fecha_programada' => 'date:Y-m-d', 'fecha_realizada' => 'date:Y-m-d', 'firmado_en' => 'datetime'];
    }

    public function contrato()
    {
        return $this->belongsTo(Contrato::class, 'contrato_id');
    }

    public function tecnico()
    {
        return $this->belongsTo(Personal::class, 'tecnico_id');
    }
}
