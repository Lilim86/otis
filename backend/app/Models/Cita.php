<?php

namespace App\Models;

class Cita extends Auditable
{
    protected $table = 'citas';

    protected $fillable = [
        'titulo', 'tipo', 'responsable', 'fecha_inicio', 'fecha_fin', 'lugar', 'con_quien',
        'telefono', 'empresa_id', 'edificio_id', 'motivo', 'situacion', 'estado',
    ];

    protected function casts(): array
    {
        return ['fecha_inicio' => 'datetime', 'fecha_fin' => 'datetime'];
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function edificio()
    {
        return $this->belongsTo(Edificio::class, 'edificio_id');
    }

    /** Citas vigentes del mismo responsable que se cruzan con el rango dado. */
    public static function cruzadas(string $responsable, $inicio, $fin, ?int $excepto = null)
    {
        return static::where('responsable', $responsable)
            ->where('situacion', '!=', 'CANCELADA')
            ->where('estado', true)
            ->where('fecha_inicio', '<', $fin)
            ->where('fecha_fin', '>', $inicio)
            ->when($excepto, fn ($q) => $q->where('id', '!=', $excepto));
    }
}
