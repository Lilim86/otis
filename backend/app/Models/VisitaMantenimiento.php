<?php

namespace App\Models;

class VisitaMantenimiento extends Auditable
{
    protected $table = 'visitas_mantenimiento';

    protected $fillable = [
        'contrato_id', 'nro_visita', 'tipo', 'fecha_programada', 'fecha_realizada', 'situacion', 'tecnico_id',
        'descripcion', 'informe', 'iniciada_en', 'calificacion', 'comentario_cliente', 'solicitada_por', 'estado',
        'prioridad', 'motivo_prioridad', 'objetivo_respuesta_en', 'asignada_automaticamente', 'orden_ruta',
    ];

    protected function casts(): array
    {
        return ['fecha_programada' => 'date:Y-m-d', 'fecha_realizada' => 'date:Y-m-d', 'firmado_en' => 'datetime', 'iniciada_en' => 'datetime', 'objetivo_respuesta_en' => 'datetime', 'asignada_automaticamente' => 'boolean'];
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
