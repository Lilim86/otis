<?php

namespace App\Models;

use App\Models\Concerns\TieneAprobacion;
use App\Models\Concerns\TieneUbicacion;

class Empresa extends Auditable
{
    use TieneAprobacion, TieneUbicacion;

    protected $table = 'empresas';

    protected $fillable = [
        'denominacion',
        'nit',
        'tipo_empresa',
        'sigla',
        'estado',
        'tipo_documento',
        'expedido_en',
        'iniciales_sucursal',
        'fecha_creacion',
        'telefono_fijo',
        'telefono_fax',
        'telefono_celular',
        'correo_contacto',
        'pagina_web',
        'pais_id',
        'departamento_id',
        'provincia_id',
        'municipio_id',
        'zona_id',
        'via_acceso_id',
        'nro_vivienda',
        'nro_piso',
        'nro_depto',
    ];

    public function contratos()
    {
        return $this->hasMany(Contrato::class, 'empresa_id');
    }
}
