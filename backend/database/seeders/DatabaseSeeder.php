<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@otis.com'],
            [
                'name' => 'Lilim Administrador',
                'username' => 'admin', // <-- ¡Esta es la línea mágica que faltaba!
                'password' => bcrypt('12345678'),
            ]
        );

        $this->call([GeografiaBaseSeeder::class, GrupoBienSeeder::class, PermisosSeeder::class]);
    }
}
