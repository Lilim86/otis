<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Arma la ubicación completa (país, departamento, provincia, municipio, zona, vía) de cualquier registro.
 * Basta con el nivel más bajo que tenga: de una zona se sube al municipio, la provincia, el departamento y el país.
 */
class UbicacionResolver
{
    private array $nombres = [];

    private array $padres = [];

    private const NIVELES = [
        'via_acceso_id' => ['via_accesos', 'zona_id'],
        'zona_id' => ['zonas', 'municipio_id'],
        'municipio_id' => ['municipios', 'provincia_id'],
        'provincia_id' => ['provincias', 'departamento_id'],
        'departamento_id' => ['departamentos', 'pais_id'],
        'pais_id' => ['paises', null],
    ];

    public const COLUMNAS = [
        'pais' => 'País', 'departamento' => 'Departamento', 'provincia' => 'Provincia',
        'municipio' => 'Municipio', 'zona' => 'Zona', 'via' => 'Vía de acceso',
    ];

    public function __construct()
    {
        foreach (self::NIVELES as $columna => [$tabla, $padre]) {
            foreach (DB::table($tabla)->get(['id', 'nombre'] + ($padre ? [2 => $padre] : [])) as $fila) {
                $this->nombres[$columna][$fila->id] = $fila->nombre;
                if ($padre) {
                    $this->padres[$columna][$fila->id] = $fila->{$padre};
                }
            }
        }
    }

    /** @param  array<string, mixed>  $ids  columnas *_id de la ubicación (las que falten se deducen hacia arriba) */
    public function resolver(array $ids): array
    {
        $actual = [];
        foreach (array_keys(self::NIVELES) as $columna) {
            $actual[$columna] = $ids[$columna] ?? null;
        }

        // Sube de nivel completando el padre que falte.
        foreach (self::NIVELES as $columna => [, $padre]) {
            if ($actual[$columna] && $padre && ! $actual[$padre]) {
                $actual[$padre] = $this->padres[$columna][$actual[$columna]] ?? null;
            }
        }

        $nombre = fn (string $columna) => $actual[$columna] ? ($this->nombres[$columna][$actual[$columna]] ?? null) : null;

        return [
            'pais' => $nombre('pais_id'),
            'departamento' => $nombre('departamento_id'),
            'provincia' => $nombre('provincia_id'),
            'municipio' => $nombre('municipio_id'),
            'zona' => $nombre('zona_id'),
            'via' => $nombre('via_acceso_id'),
            '_departamento_id' => $actual['departamento_id'],
        ];
    }

    /** Ubicación de un modelo con columnas *_id (usa solo las que existan en él). */
    public function de(?object $modelo): array
    {
        return $this->resolver($modelo ? $modelo->only(array_keys(self::NIVELES)) : []);
    }
}
