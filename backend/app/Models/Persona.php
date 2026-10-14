<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;
use App\Models\Concerns\TieneUbicacion;

class Persona extends Auditable
{
    use TieneAprobacion, TieneUbicacion;

    protected $table = 'personas';

    protected $fillable = [
        'tipo_documento', 'numero_documento', 'expedido_en', 'primer_apellido', 'segundo_apellido', 'nombres',
        'iniciales', 'tipo_persona', 'nit', 'fecha_nacimiento', 'cargo_puesto', 'telefono_fijo', 'telefono_oficina',
        'telefono_celular', 'correo_personal', 'correo_institucional', 'nacionalidad_id',
        'pais_id', 'departamento_id', 'provincia_id', 'municipio_id', 'zona_id', 'via_acceso_id',
        'edificio_id', 'nro_vivienda', 'nro_piso', 'nro_depto',
    ];

    protected function casts(): array
    {
        return ['fecha_nacimiento' => 'date:Y-m-d'];
    }

    public function nacionalidad()
    {
        return $this->belongsTo(Pais::class, 'nacionalidad_id');
    }

    public function edificio()
    {
        return $this->belongsTo(Edificio::class, 'edificio_id');
    }

    /** Apellidos y nombres, como se lista en el SOFIA. */
    public function getNombreCompletoAttribute(): string
    {
        return implode(' ', array_filter([$this->primer_apellido, $this->segundo_apellido, $this->nombres]));
    }
}
