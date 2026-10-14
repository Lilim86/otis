<?php

namespace Database\Seeders;

use App\Models\GrupoBien;
use Illuminate\Database\Seeder;

class GrupoBienSeeder extends Seeder
{
    /** Clasificador de bienes: códigos tomados de los ejemplos del SOFIA (10000, 40000, 43340...). */
    public function run(): void
    {
        $arbol = [
            ['10000', 'SERVICIOS Y GASTOS DE PERSONAL', [
                ['11000', 'REMUNERACIONES', [['11700', 'Sueldos']]],
            ]],
            ['30000', 'MATERIALES Y SUMINISTROS', []],
            ['40000', 'ACTIVOS REALIZABLES Y EQUIPOS', [
                ['43000', 'EQUIPOS', [['43340', 'Equipo de Elevación']]],
            ]],
        ];

        foreach ($arbol as [$codigo, $nombre, $hijos]) {
            $grupo = GrupoBien::firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'nivel' => 1]);

            foreach ($hijos as [$codigo1, $nombre1, $nietos]) {
                $sub1 = GrupoBien::firstOrCreate(['codigo' => $codigo1], ['nombre' => $nombre1, 'nivel' => 2, 'padre_id' => $grupo->id]);

                foreach ($nietos as [$codigo2, $nombre2]) {
                    GrupoBien::firstOrCreate(['codigo' => $codigo2], ['nombre' => $nombre2, 'nivel' => 3, 'padre_id' => $sub1->id]);
                }
            }
        }
    }
}
