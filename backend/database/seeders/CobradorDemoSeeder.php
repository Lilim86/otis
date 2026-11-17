<?php

namespace Database\Seeders;

use App\Models\Edificio;
use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Enlaza el usuario cobrador.demo con una persona cobradora (datos INVENTADOS) y le asigna los edificios
 * que todavía no tienen cobrador. Es repetible: no pisa las asignaciones hechas a mano.
 * Ejecutar a mano: php artisan db:seed --class=CobradorDemoSeeder
 */
class CobradorDemoSeeder extends Seeder
{
    public function run(): void
    {
        $usuario = User::where('username', 'cobrador.demo')->first();
        if (! $usuario) {
            $this->command?->warn('No existe el usuario cobrador.demo: corre primero ClienteDemoSeeder.');

            return;
        }

        $persona = Persona::firstOrCreate(
            ['tipo_documento' => 'CI', 'numero_documento' => 'DEMO-COB-1'],
            ['primer_apellido' => 'Demo', 'nombres' => 'Cobrador', 'tipo_persona' => 'COBRADOR', 'cargo_puesto' => 'Cobrador',
                'telefono_celular' => '70000000', 'correo_personal' => 'cobrador.demo@example.com'],
        );

        $usuario->forceFill(['persona_id' => $persona->id])->save();
        $asignados = Edificio::whereNull('cobrador_id')->update(['cobrador_id' => $persona->id]);

        $this->command?->info("cobrador.demo enlazado a su persona cobradora; {$asignados} edificio(s) asignados.");
    }
}
