<?php

namespace Tests\Feature\Api;

use App\Livewire\GestionUsuarios;
use App\Models\Acceso;
use App\Models\Pais;
use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeguridadApiTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $nombre): User
    {
        return User::create(['name' => $nombre, 'username' => $nombre, 'email' => "{$nombre}@otis.com", 'password' => 'secret123']);
    }

    /** El id 1 es el Super Administrador. */
    private function superAdmin(): User
    {
        return $this->usuario('admin');
    }

    public function test_el_seeder_crea_un_permiso_por_operacion_de_cada_modulo(): void
    {
        $this->superAdmin();
        $this->seed(PermisosSeeder::class);

        foreach (['paises.ver', 'paises.crear', 'paises.editar', 'paises.eliminar', 'paises.restaurar', 'compras.aprobar', 'compras.anular', 'seguridad.administrar'] as $permiso) {
            $this->assertTrue(Permission::where('name', $permiso)->exists(), "Falta {$permiso}");
        }
        $this->assertFalse(Permission::where('name', 'paises.aprobar')->exists()); // paises no tiene flujo de aprobación
        $this->assertSame(Permission::count(), Role::findByName('Administrador', 'web')->permissions()->count());
        $this->assertTrue(User::where('username', 'admin')->first()->hasRole('Administrador'));
    }

    public function test_con_privilegios_desactivados_todos_operan(): void
    {
        config(['sofia.permisos_activos' => false]);
        Sanctum::actingAs($this->superAdmin());
        $sinRol = $this->usuario('ana');
        Sanctum::actingAs($sinRol);

        $this->postJson('/api/paises', ['nombre' => 'Bolivia'])->assertCreated();
    }

    public function test_con_privilegios_activados_cada_operacion_exige_su_permiso(): void
    {
        $this->superAdmin();
        $this->seed(PermisosSeeder::class);
        config(['sofia.permisos_activos' => true]);

        $lector = $this->usuario('lector');
        Role::create(['name' => 'Lector', 'guard_name' => 'web'])->givePermissionTo(['paises.ver']);
        $lector->assignRole('Lector');
        Sanctum::actingAs($lector);

        $this->getJson('/api/paises')->assertOk();
        $this->getJson('/api/paises/papelera')->assertOk();
        $this->postJson('/api/paises', ['nombre' => 'X'])->assertForbidden()->assertJsonPath('message', fn ($m) => str_contains($m, 'paises.crear'));
        $this->putJson('/api/paises/1', [])->assertForbidden();
        $this->deleteJson('/api/paises/1')->assertForbidden();
        $this->getJson('/api/departamentos')->assertForbidden(); // otro módulo
        $this->getJson('/api/catalogos')->assertOk(); // rutas fuera de módulos no se afectan
    }

    public function test_un_usuario_sin_roles_no_puede_nada_y_el_super_admin_puede_todo(): void
    {
        $admin = $this->superAdmin();
        $this->seed(PermisosSeeder::class);
        config(['sofia.permisos_activos' => true]);

        Sanctum::actingAs($this->usuario('ana'));
        $this->getJson('/api/paises')->assertForbidden();

        Sanctum::actingAs($admin);
        $this->postJson('/api/paises', ['nombre' => 'Bolivia'])->assertCreated();
    }

    public function test_los_permisos_cubren_alta_baja_restaurar_y_aprobar(): void
    {
        $this->superAdmin();
        $this->seed(PermisosSeeder::class);
        config(['sofia.permisos_activos' => true]);

        $editor = $this->usuario('editor');
        Role::create(['name' => 'Editor', 'guard_name' => 'web'])->givePermissionTo(['paises.ver', 'paises.editar', 'personas.ver', 'personas.aprobar']);
        $editor->assignRole('Editor');
        Sanctum::actingAs($editor);
        $pais = Pais::create(['nombre' => 'Bolivia']);

        $this->postJson("/api/paises/{$pais->id}/baja")->assertOk(); // alta/baja = editar
        $this->postJson("/api/paises/{$pais->id}/restaurar")->assertForbidden();
        $this->postJson('/api/personas/1/aprobar')->assertStatus(404); // pasó el permiso; la persona no existe
        $this->postJson('/api/personas/1/anular')->assertForbidden();
    }

    // ------------------------------------------------------------- roles y asignación

    public function test_solo_quien_administra_la_seguridad_gestiona_roles(): void
    {
        $this->superAdmin();
        Sanctum::actingAs($this->usuario('ana'));

        // Aunque los privilegios por operación estén desactivados, la seguridad siempre está protegida.
        $this->getJson('/api/seguridad/roles')->assertForbidden();
        $this->postJson('/api/seguridad/roles', ['name' => 'Tramposo'])->assertForbidden();
        $this->putJson('/api/seguridad/usuarios/2/roles', ['roles' => ['Administrador']])->assertForbidden();
        $this->getJson('/api/seguridad/accesos')->assertForbidden();
    }

    public function test_crud_de_roles_y_asignacion_a_usuarios(): void
    {
        $admin = $this->superAdmin();
        $this->seed(PermisosSeeder::class);
        Sanctum::actingAs($admin);
        $ana = $this->usuario('ana');

        $id = $this->postJson('/api/seguridad/roles', ['name' => 'Auditor externo', 'permisos' => ['cobranzas.ver', 'cobranzas.crear']])->assertCreated()
            ->assertJsonPath('data.permisos', ['cobranzas.crear', 'cobranzas.ver'])->json('data.id');
        $this->postJson('/api/seguridad/roles', ['name' => 'Auditor externo'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/seguridad/roles', ['name' => 'Raro', 'permisos' => ['inventado.todo']])->assertUnprocessable();

        $this->putJson("/api/seguridad/roles/{$id}", ['name' => 'Auditor externo', 'permisos' => ['cobranzas.ver']])->assertOk()->assertJsonCount(1, 'data.permisos');
        $this->putJson("/api/seguridad/usuarios/{$ana->id}/roles", ['roles' => ['Auditor externo']])->assertOk()->assertJsonPath('data.roles', ['Auditor externo']);
        $this->putJson("/api/seguridad/usuarios/{$ana->id}/roles", ['roles' => ['NoExiste']])->assertUnprocessable();
        $this->putJson('/api/seguridad/usuarios/1/roles', ['roles' => []])->assertForbidden(); // el super admin no se toca

        $this->deleteJson("/api/seguridad/roles/{$id}")->assertStatus(409); // tiene usuarios
        $this->putJson("/api/seguridad/usuarios/{$ana->id}/roles", ['roles' => []])->assertOk();
        $this->deleteJson("/api/seguridad/roles/{$id}")->assertNoContent();

        $admin_rol = Role::findByName('Administrador', 'web');
        $this->putJson("/api/seguridad/roles/{$admin_rol->id}", ['name' => 'Otro'])->assertForbidden();
        $this->deleteJson("/api/seguridad/roles/{$admin_rol->id}")->assertForbidden();
    }

    public function test_matriz_de_permisos_agrupada_y_perfil_con_permisos(): void
    {
        $admin = $this->superAdmin();
        $this->seed(PermisosSeeder::class);
        Sanctum::actingAs($admin);

        $this->getJson('/api/seguridad/permisos')->assertOk()->assertJsonFragment(['modulo' => 'paises']);

        $this->getJson('/api/user')->assertOk()
            ->assertJsonPath('es_super_admin', true)->assertJsonPath('roles', ['Administrador'])
            ->assertJsonPath('permisos_activos', false);
    }

    // -------------------------------------------------------------- control de accesos

    public function test_la_bitacora_registra_ingresos_fallos_y_tokens(): void
    {
        $admin = $this->superAdmin();

        $this->postJson('/api/auth/token', ['username' => 'admin', 'password' => 'mala'])->assertUnprocessable();
        config(['otp.requerido' => false]);
        $this->postJson('/api/auth/token', ['username' => 'admin', 'password' => 'secret123'])->assertOk();

        $this->assertSame(['FALLO', 'TOKEN'], Acceso::orderBy('id')->pluck('evento')->all());
        $this->assertSame('admin', Acceso::first()->username_intentado);
        $this->assertNull(Acceso::first()->user_id);
        $this->assertSame($admin->id, Acceso::latest('id')->first()->user_id);
    }

    public function test_consulta_de_la_bitacora_con_filtros(): void
    {
        $admin = $this->superAdmin();
        Sanctum::actingAs($admin);
        Acceso::create(['user_id' => $admin->id, 'evento' => 'LOGIN', 'ip' => '10.0.0.1']);
        Acceso::create(['username_intentado' => 'intruso', 'evento' => 'FALLO', 'ip' => '10.0.0.9']);

        $this->getJson('/api/seguridad/accesos')->assertOk()->assertJsonPath('total', 2)->assertJsonPath('data.0.evento', 'FALLO');
        $this->getJson('/api/seguridad/accesos?evento=LOGIN')->assertJsonPath('total', 1)->assertJsonPath('data.0.usuario.username', 'admin');
        $this->getJson("/api/seguridad/accesos?user_id={$admin->id}")->assertJsonPath('total', 1);
        $this->getJson('/api/seguridad/accesos?evento=HACK')->assertUnprocessable();
        $this->getJson('/api/seguridad/accesos?desde=2000-01-01&hasta=1999-01-01')->assertUnprocessable();
    }

    public function test_el_login_web_deja_rastro_de_ingreso_y_de_fallo(): void
    {
        $this->superAdmin();

        $this->post('/login', ['username' => 'admin', 'password' => 'incorrecta']);
        $this->post('/login', ['username' => 'admin', 'password' => 'secret123']);

        $eventos = Acceso::orderBy('id')->pluck('evento')->all();
        $this->assertContains('FALLO', $eventos);
        $this->assertContains('LOGIN', $eventos);
    }

    public function test_el_login_acepta_usuario_o_correo(): void
    {
        $this->superAdmin();
        $this->usuario('ana');

        foreach (['ana', 'ana@otis.com', 'ANA@OTIS.COM'] as $login) {
            $this->post('/login', ['username' => $login, 'password' => 'secret123'])->assertRedirect();
            $this->assertAuthenticated();
            $this->post('/logout');
        }

        $this->post('/login', ['username' => 'ana@otis.com', 'password' => 'mala']);
        $this->assertGuest();

        config(['otp.requerido' => false]);
        $this->postJson('/api/auth/token', ['username' => 'ana@otis.com', 'password' => 'secret123'])->assertOk();
    }

    public function test_el_seeder_crea_roles_por_area_con_consulta_en_el_resto(): void
    {
        $this->seed(PermisosSeeder::class);

        $comercial = Role::findByName('Comercial', 'web');
        $this->assertTrue($comercial->hasPermissionTo('empresas.crear'));
        $this->assertTrue($comercial->hasPermissionTo('paises.ver'));
        $this->assertFalse($comercial->hasPermissionTo('paises.crear'));
        $this->assertFalse(Role::findByName('Consulta', 'web')->hasPermissionTo('usuarios.ver'));
    }

    public function test_la_pantalla_de_usuarios_asigna_modifica_y_elimina(): void
    {
        Mail::fake();
        $this->seed(PermisosSeeder::class);
        $admin = $this->superAdmin();
        $this->actingAs($admin);

        Livewire::test(GestionUsuarios::class)
            ->set('name', 'Ana Perez')->set('email', 'ana@example.com')->set('rol', 'Comercial')
            ->call('guardar')->assertHasNoErrors();

        $ana = User::where('email', 'ana@example.com')->first();
        $this->assertTrue($ana->hasRole('Comercial'));

        Livewire::test(GestionUsuarios::class)
            ->call('editar', $ana->id)->set('rol', 'Técnico')->set('name', 'Ana P.')->call('guardar');
        $ana->refresh();
        $this->assertTrue($ana->hasRole('Técnico'));
        $this->assertFalse($ana->hasRole('Comercial'));
        $this->assertSame('Ana P.', $ana->name);

        Livewire::test(GestionUsuarios::class)->call('eliminar', $ana->id);
        $this->assertSoftDeleted('users', ['id' => $ana->id]);
    }

    public function test_la_pantalla_de_usuarios_es_solo_para_quien_administra_seguridad(): void
    {
        $this->seed(PermisosSeeder::class);
        $this->superAdmin();
        $otro = User::factory()->create();
        $this->actingAs($otro)->get('/seguridad/usuarios')->assertForbidden();
    }

    public function test_cobrador_y_secretaria_solo_tienen_lo_que_necesitan(): void
    {
        $this->seed(PermisosSeeder::class);

        $cobrador = Role::findByName('Cobrador', 'web');
        $this->assertTrue($cobrador->hasPermissionTo('cobranzas.crear'));
        $this->assertFalse($cobrador->hasPermissionTo('cobranzas.eliminar'));
        $this->assertFalse($cobrador->hasPermissionTo('paises.ver'));
        $this->assertFalse($cobrador->hasPermissionTo('citas.ver'));

        $secretaria = Role::findByName('Secretaría', 'web');
        $this->assertTrue($secretaria->hasPermissionTo('citas.crear'));
        $this->assertFalse($secretaria->hasPermissionTo('cobranzas.ver'));
        $this->assertFalse(Role::findByName('Consulta', 'web')->hasPermissionTo('citas.ver'));
    }

    public function test_el_menu_y_las_pantallas_siguen_al_rol(): void
    {
        config(['sofia.permisos_activos' => true]);
        $this->seed(PermisosSeeder::class);
        $this->superAdmin();
        $cobrador = User::factory()->create();
        $cobrador->assignRole('Cobrador');

        $this->actingAs($cobrador)->get('/financiero/cobranzas')->assertOk();
        $this->actingAs($cobrador)->get('/secretaria/agenda')->assertForbidden();
        $this->actingAs($cobrador)->get('/planificacion/paises')->assertForbidden();
    }
}
