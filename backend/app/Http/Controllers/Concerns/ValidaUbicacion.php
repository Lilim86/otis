<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Validation\Rule;

/**
 * Reglas de la ubicación geográfica (país > departamento > provincia > municipio > zona > vía de acceso).
 * Cada nivel debe pertenecer al nivel elegido arriba, para no guardar combinaciones imposibles.
 */
trait ValidaUbicacion
{
    /** Relaciones a cargar en las respuestas. */
    protected function relacionesUbicacion(): array
    {
        return ['pais', 'departamento', 'provincia', 'municipio', 'zona', 'viaAcceso'];
    }

    protected function reglasUbicacion(): array
    {
        $niveles = [
            'pais_id' => ['paises', null, null],
            'departamento_id' => ['departamentos', 'pais_id', 'pais_id'],
            'provincia_id' => ['provincias', 'departamento_id', 'departamento_id'],
            'municipio_id' => ['municipios', 'provincia_id', 'provincia_id'],
            'zona_id' => ['zonas', 'municipio_id', 'municipio_id'],
            'via_acceso_id' => ['via_accesos', 'zona_id', 'zona_id'],
        ];

        $reglas = [];
        foreach ($niveles as $campo => [$tabla, $campoPadre, $columnaPadre]) {
            $regla = Rule::exists($tabla, 'id')->whereNull('deleted_at');
            if ($campoPadre && request()->filled($campoPadre)) {
                $regla->where($columnaPadre, request()->input($campoPadre));
            }
            $reglas[$campo] = ['nullable', $regla];
        }

        return $reglas + [
            'nro_vivienda' => ['nullable', 'string', 'max:20'],
            'nro_piso' => ['nullable', 'string', 'max:20'],
            'nro_depto' => ['nullable', 'string', 'max:20'],
        ];
    }
}
