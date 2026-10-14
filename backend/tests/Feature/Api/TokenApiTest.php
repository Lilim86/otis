<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenApiTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(array $extra = []): User
    {
        return User::create($extra + ['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']);
    }

    public function test_login_por_token_y_uso_de_la_api(): void
    {
        $this->usuario();

        $token = $this->postJson('/api/auth/token', ['username' => 'admin', 'password' => 'secret123', 'device_name' => 'postman'])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.username', 'admin')
            ->assertJsonMissingPath('user.password')
            ->json('token');

        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('username', 'admin');
        $this->withToken($token)->getJson('/api/paises')->assertOk();
        $this->withToken($token)->postJson('/api/paises', ['nombre' => 'Bolivia'])->assertCreated();
    }

    public function test_rechaza_credenciales_incorrectas(): void
    {
        $this->usuario();

        $this->postJson('/api/auth/token', ['username' => 'admin', 'password' => 'mala'])
            ->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->postJson('/api/auth/token', ['username' => 'noexiste', 'password' => 'secret123'])
            ->assertUnprocessable();
        $this->postJson('/api/auth/token', [])->assertUnprocessable()->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_rechaza_usuarios_inactivos_y_con_dos_factores(): void
    {
        $this->usuario(['username' => 'inactivo', 'email' => 'i@otis.com', 'estado' => false]);
        $this->usuario(['username' => 'conmfa', 'email' => 'm@otis.com'])
            ->forceFill(['two_factor_secret' => 'secreto'])->save();

        $this->postJson('/api/auth/token', ['username' => 'inactivo', 'password' => 'secret123'])->assertUnprocessable();
        $this->postJson('/api/auth/token', ['username' => 'conmfa', 'password' => 'secret123'])->assertUnprocessable();
    }

    public function test_cerrar_sesion_revoca_el_token(): void
    {
        $this->usuario();
        $token = $this->postJson('/api/auth/token', ['username' => 'admin', 'password' => 'secret123'])->json('token');

        $this->withToken($token)->deleteJson('/api/auth/token')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_sin_token_no_se_puede_usar_la_api(): void
    {
        $this->getJson('/api/paises')->assertUnauthorized();
        $this->deleteJson('/api/auth/token')->assertUnauthorized();
    }
}
