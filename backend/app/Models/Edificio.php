<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;
use App\Models\Concerns\TieneUbicacion;

class Edificio extends Auditable
{
    use TieneAprobacion, TieneUbicacion;

    protected $table = 'edificios';

    protected $fillable = [
        'codigo_edificio',
        'denominacion',
        'tipo_edificacion',
        'estado',
        'codigo_administrativo',
        'pais_id',
        'departamento_id',
        'provincia_id',
        'municipio_id',
        'zona_id',
        'via_acceso_id',
        'nro_edificio',
        'referencia_cercana',
        'propietario_id',
        'empresa_id',
        'contacto_cobranzas_id',
        'contacto_tecnico_id',
        'latitud',
        'longitud',
        'altura_msnm',
    ];

    public function contratos()
    {
        return $this->hasMany(Contrato::class, 'edificio_id');
    }

    public function equipos()
    {
        return $this->hasMany(Equipo::class, 'edificio_id');
    }

    public function propietario()
    {
        return $this->belongsTo(Persona::class, 'propietario_id');
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function contactoCobranzas()
    {
        return $this->belongsTo(Persona::class, 'contacto_cobranzas_id');
    }

    public function contactoTecnico()
    {
        return $this->belongsTo(Persona::class, 'contacto_tecnico_id');
    }
}
