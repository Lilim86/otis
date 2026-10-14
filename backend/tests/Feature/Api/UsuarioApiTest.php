<?php

namespace Tests\Feature\Api;

use App\Mail\CredencialesUsuarioMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsuarioApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_requiere_autenticacion(): void
    {
        $this->getJson('/api/usuarios')->assertUnauthorized();
    }

    public function test_lista_y_busca_usuarios(): void
    {
        $this->admin();
        User::create(['name' => 'Maria Perez', 'username' => 'maria', 'email' => 'maria@otis.com', 'password' => 'x']);

        $this->getJson('/api/usuarios')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/usuarios?q=MARIA')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_crea_usuario_y_envia_credenciales_por_correo(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->postJson('/api/usuarios', ['name' => 'Juan Quispe', 'email' => 'juan@otis.com'])
            ->assertCreated()
            ->assertJsonPath('data.username', 'juan')
            ->assertJsonPath('data.estado', true)
            ->assertJsonPath('data.creado_por', $admin->id)
            ->assertJsonMissingPath('data.password');

        Mail::assertSent(CredencialesUsuarioMail::class);
        $this->assertDatabaseHas('users', ['email' => 'juan@otis.com']);
    }

    public function test_valida_correo_repetido(): void
    {
        Mail::fake();
        $this->admin();

        $this->postJson('/api/usuarios', ['name' => 'Otro', 'email' => 'admin@otis.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_actualiza_usuario_y_registra_quien_lo_modifico(): void
    {
        $admin = $this->admin();
        $usuario = User::create(['name' => 'Ana', 'username' => 'ana', 'email' => 'ana@otis.com', 'password' => 'x']);

        $this->putJson("/api/usuarios/{$usuario->id}", ['name' => 'Ana Rojas', 'estado' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ana Rojas')
            ->assertJsonPath('data.estado', false)
            ->assertJsonPath('data.actualizado_por', $admin->id);
    }

    public function test_elimina_con_borrado_suave(): void
    {
        $this->admin();
        $usuario = User::create(['name' => 'Ana', 'username' => 'ana', 'email' => 'ana@otis.com', 'password' => 'x']);

        $this->deleteJson("/api/usuarios/{$usuario->id}")->assertNoContent();

        $this->assertSoftDeleted('users', ['id' => $usuario->id]);
        $this->getJson("/api/usuarios/{$usuario->id}")->assertNotFound();
    }

    public function test_no_permite_eliminar_al_super_administrador(): void
    {
        $this->admin();
        $otro = User::create(['name' => 'Ana', 'username' => 'ana', 'email' => 'ana@otis.com', 'password' => 'x']);
        Sanctum::actingAs($otro);

        $this->deleteJson('/api/usuarios/1')->assertForbidden();
        $this->assertNotSoftDeleted('users', ['id' => 1]);
    }
}
