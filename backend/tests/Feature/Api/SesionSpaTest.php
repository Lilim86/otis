<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SesionSpaTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND = 'http://127.0.0.1:5173';

    public function test_el_frontend_inicia_sesion_y_consulta_la_api(): void
    {
        User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']);
        $cabeceras = ['Origin' => self::FRONTEND, 'Referer' => self::FRONTEND.'/'];

        $this->withHeaders($cabeceras)->getJson('/api/user')->assertUnauthorized();

        $this->withHeaders($cabeceras)->postJson('/login', ['username' => 'admin', 'password' => 'secret123'])->assertOk();

        $this->withHeaders($cabeceras)->getJson('/api/user')->assertOk()->assertJsonPath('username', 'admin');
        $this->withHeaders($cabeceras)->getJson('/api/paises')->assertOk();
    }

    public function test_cors_permite_al_frontend_con_credenciales(): void
    {
        $this->withHeaders(['Origin' => self::FRONTEND])->getJson('/api/user')
            ->assertHeader('Access-Control-Allow-Origin', self::FRONTEND)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');

        $this->withHeaders(['Origin' => 'http://sitio-malicioso.com'])->getJson('/api/user')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}
