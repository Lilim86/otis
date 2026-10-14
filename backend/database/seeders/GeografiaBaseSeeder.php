<?php

namespace Database\Seeders;

use App\Models\Departamento;
use App\Models\Pais;
use Illuminate\Database\Seeder;

class GeografiaBaseSeeder extends Seeder
{
    public function run(): void
    {
        $pais = Pais::firstOrCreate(['nombre' => 'BOLIVIA'], ['codigo_iso' => 'BO']);

        // Las 9 regionales del SOFIA, en el mismo orden (1. Chuquisaca ... 9. Pando).
        foreach (['CHUQUISACA', 'LA PAZ', 'COCHABAMBA', 'ORURO', 'POTOSI', 'TARIJA', 'SANTA CRUZ', 'BENI', 'PANDO'] as $nombre) {
            Departamento::firstOrCreate(['nombre' => $nombre], ['pais_id' => $pais->id]);
        }
    }
}
