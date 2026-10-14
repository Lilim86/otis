<?php

namespace Tests\Feature\Api;

use App\Models\Contrato;
use App\Models\Departamento;
use App\Models\Edificio;
use App\Models\Empresa;
use App\Models\Pais;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PapeleraYFiltrosApiTest extends TestCase
{
    use RefreshDatabase;

    private const RECURSOS = [
        'usuarios', 'paises', 'departamentos', 'provincias', 'municipios', 'zonas', 'vias-acceso',
        'clasificadores-tecnicos', 'mantenimientos', 'ascensores', 'emergencias', 'equipos', 'repuestos',
        'ordenes-mantenimiento', 'cargos', 'profesiones', 'personal', 'empresas', 'edificios',
        'contratos', 'insumos', 'bancos', 'cuentas-bancarias', 'cobranzas',
    ];

    private function ingresar(): User
    {
        $admin = User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    // ---------------------------------------------------------------- papelera

    public function test_todos_los_modulos_tienen_papelera_protegida(): void
    {
        foreach (self::RECURSOS as $recurso) {
            $this->getJson("/api/{$recurso}/papelera")->assertUnauthorized();
        }

        $this->ingresar();

        foreach (self::RECURSOS as $recurso) {
            $this->getJson("/api/{$recurso}/papelera")->assertOk()->assertJsonStructure(['data', 'meta']);
        }
    }

    public function test_eliminar_manda_a_la_papelera_y_restaurar_la_devuelve(): void
    {
        $admin = $this->ingresar();
        $pais = Pais::create(['nombre' => 'Bolivia']);

        $this->deleteJson("/api/paises/{$pais->id}")->assertNoContent();

        $this->getJson('/api/paises')->assertJsonCount(0, 'data');
        $this->getJson('/api/paises/papelera')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nombre', 'Bolivia')
            ->assertJsonPath('data.0.actualizado_por', $admin->id);
        $this->putJson("/api/paises/{$pais->id}", ['nombre' => 'X'])->assertNotFound();

        $this->postJson("/api/paises/{$pais->id}/restaurar")->assertOk()->assertJsonPath('data.deleted_at', null);
        $this->getJson('/api/paises')->assertJsonCount(1, 'data');
        $this->getJson('/api/paises/papelera')->assertJsonCount(0, 'data');
    }

    public function test_restaurar_algo_que_no_esta_en_la_papelera_da_404(): void
    {
        $this->ingresar();
        $pais = Pais::create(['nombre' => 'Bolivia']);

        $this->postJson("/api/paises/{$pais->id}/restaurar")->assertNotFound();
        $this->postJson('/api/paises/999/restaurar')->assertNotFound();
    }

    public function test_no_se_elimina_un_registro_con_dependientes(): void
    {
        $this->ingresar();
        $pais = Pais::create(['nombre' => 'Bolivia']);
        $departamento = Departamento::create(['nombre' => 'La Paz', 'pais_id' => $pais->id]);

        $this->deleteJson("/api/paises/{$pais->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'No se puede eliminar: aún tiene registros en departamentos.');
        $this->assertNotSoftDeleted('paises', ['id' => $pais->id]);

        $this->deleteJson("/api/departamentos/{$departamento->id}")->assertNoContent();
        $this->deleteJson("/api/paises/{$pais->id}")->assertNoContent();
    }

    public function test_no_se_restaura_un_hijo_si_su_padre_sigue_en_la_papelera(): void
    {
        $this->ingresar();
        $pais = Pais::create(['nombre' => 'Bolivia']);
        $departamento = Departamento::create(['nombre' => 'La Paz', 'pais_id' => $pais->id]);
        $this->deleteJson("/api/departamentos/{$departamento->id}")->assertNoContent();
        $this->deleteJson("/api/paises/{$pais->id}")->assertNoContent();

        $this->postJson("/api/departamentos/{$departamento->id}/restaurar")
            ->assertStatus(409)->assertJsonPath('message', fn ($m) => str_contains($m, 'país'));

        $this->postJson("/api/paises/{$pais->id}/restaurar")->assertOk();
        $this->postJson("/api/departamentos/{$departamento->id}/restaurar")->assertOk();
    }

    public function test_no_se_puede_apuntar_a_un_registro_eliminado(): void
    {
        $this->ingresar();
        $pais = Pais::create(['nombre' => 'Bolivia']);
        $this->deleteJson("/api/paises/{$pais->id}")->assertNoContent();

        $this->postJson('/api/departamentos', ['nombre' => 'La Paz', 'pais_id' => $pais->id])
            ->assertUnprocessable()->assertJsonValidationErrors('pais_id');
    }

    // ------------------------------------------------------------- alta y baja

    public function test_dar_de_baja_y_de_alta(): void
    {
        $this->ingresar();
        $id = Pais::create(['nombre' => 'Bolivia'])->id;
        Pais::create(['nombre' => 'Peru']);

        $this->postJson("/api/paises/{$id}/baja")->assertOk()->assertJsonPath('data.estado', false);
        $this->getJson('/api/paises?estado=0')->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Bolivia');
        $this->getJson('/api/paises?estado=1')->assertJsonCount(1, 'data');
        // Dar de baja no elimina: sigue en el listado general y no está en la papelera.
        $this->getJson('/api/paises')->assertJsonCount(2, 'data');
        $this->getJson('/api/paises/papelera')->assertJsonCount(0, 'data');

        $this->postJson("/api/paises/{$id}/alta")->assertOk()->assertJsonPath('data.estado', true);
    }

    public function test_cobranzas_no_usa_alta_y_baja(): void
    {
        $this->ingresar();

        $this->postJson('/api/cobranzas/1/baja')->assertUnprocessable();
        $this->postJson('/api/cobranzas/1/alta')->assertUnprocessable();
    }

    public function test_baja_de_usuario_le_quita_el_acceso(): void
    {
        $this->ingresar();
        $otro = User::create(['name' => 'Ana', 'username' => 'ana', 'email' => 'ana@otis.com', 'password' => 'secret123']);
        $this->postJson('/api/auth/token', ['username' => 'ana', 'password' => 'secret123'])->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->postJson("/api/usuarios/{$otro->id}/baja")->assertOk()->assertJsonPath('data.estado', false);

        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->postJson('/api/auth/token', ['username' => 'ana', 'password' => 'secret123'])->assertUnprocessable();
        $this->postJson('/login', ['username' => 'ana', 'password' => 'secret123'])->assertUnprocessable();
    }

    public function test_no_se_puede_dar_de_baja_al_super_administrador(): void
    {
        $this->ingresar();

        $this->postJson('/api/usuarios/1/baja')->assertForbidden();
        $this->assertTrue((bool) User::find(1)->estado);
    }

    // ----------------------------------------------------------------- filtros

    public function test_ordenar_y_paginar(): void
    {
        $this->ingresar();
        foreach (['Chile', 'Argentina', 'Brasil'] as $nombre) {
            Pais::create(['nombre' => $nombre]);
        }

        $this->getJson('/api/paises?ordenar_por=nombre&direccion=asc')
            ->assertJsonPath('data.0.nombre', 'Argentina')->assertJsonPath('data.2.nombre', 'Chile');
        $this->getJson('/api/paises?ordenar_por=nombre&direccion=desc')->assertJsonPath('data.0.nombre', 'Chile');
        $this->getJson('/api/paises?per_page=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
    }

    public function test_los_filtros_se_validan(): void
    {
        $this->ingresar();

        $this->getJson('/api/paises?ordenar_por=password')->assertUnprocessable()->assertJsonValidationErrors('ordenar_por');
        $this->getJson('/api/paises?direccion=arriba')->assertUnprocessable()->assertJsonValidationErrors('direccion');
        $this->getJson('/api/paises?per_page=1000')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/paises?estado=quizas')->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->getJson('/api/paises?creado_desde=ayer')->assertUnprocessable()->assertJsonValidationErrors('creado_desde');
        $this->getJson('/api/paises?creado_desde=2026-02-01&creado_hasta=2026-01-01')->assertUnprocessable()->assertJsonValidationErrors('creado_hasta');
    }

    public function test_filtro_por_fecha_de_creacion_y_por_autor(): void
    {
        $admin = $this->ingresar();
        $this->postJson('/api/paises', ['nombre' => 'Bolivia'])->assertCreated();
        $hoy = now()->toDateString();

        $this->getJson("/api/paises?creado_desde={$hoy}&creado_hasta={$hoy}")->assertJsonCount(1, 'data');
        $this->getJson('/api/paises?creado_hasta=2000-01-01')->assertJsonCount(0, 'data');
        $this->getJson('/api/paises?creado_desde=2100-01-01')->assertJsonCount(0, 'data');
        $this->getJson("/api/paises?creado_por={$admin->id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/paises?creado_por=999')->assertJsonCount(0, 'data');
    }

    public function test_filtro_por_rango_de_fechas_del_modulo(): void
    {
        $this->ingresar();
        $empresa = Empresa::create(['denominacion' => 'ACME', 'nit' => '1', 'tipo_empresa' => 'CLIENTE']);
        $edificio = Edificio::create(['codigo_edificio' => '10101-0', 'denominacion' => 'Casa', 'tipo_edificacion' => 'Edificio']);
        foreach (['2026-01-01', '2026-06-01'] as $inicio) {
            Contrato::create(['empresa_id' => $empresa->id, 'edificio_id' => $edificio->id, 'fecha_inicio' => $inicio, 'fecha_fin' => '2027-01-01', 'monto_total' => 1]);
        }

        $this->getJson('/api/contratos?fecha_inicio_desde=2026-05-01')->assertJsonCount(1, 'data');
        $this->getJson('/api/contratos?fecha_inicio_desde=2026-01-01&fecha_inicio_hasta=2026-12-31')->assertJsonCount(2, 'data');
        $this->getJson("/api/contratos?empresa_id={$empresa->id}&edificio_id=999")->assertJsonCount(0, 'data');
    }

    public function test_la_papelera_tambien_se_filtra(): void
    {
        $this->ingresar();
        foreach (['Bolivia', 'Brasil', 'Chile'] as $nombre) {
            $this->deleteJson('/api/paises/'.Pais::create(['nombre' => $nombre])->id);
        }

        $this->getJson('/api/paises/papelera?q=bra')->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Brasil');
        $this->getJson('/api/paises/papelera?all=1')->assertJsonCount(3, 'data');
    }

    // ---------------------------------------------------------------- mensajes

    public function test_los_mensajes_de_validacion_estan_en_espanol(): void
    {
        $this->ingresar();
        Pais::create(['nombre' => 'Bolivia']);

        $this->postJson('/api/paises', [])
            ->assertUnprocessable()->assertJsonPath('errors.nombre.0', 'El campo nombre es obligatorio.');
        $this->postJson('/api/paises', ['nombre' => 'Bolivia'])
            ->assertJsonPath('errors.nombre.0', 'Ya existe un registro con ese valor en nombre (puede estar en la papelera).');
        $this->postJson('/api/paises', ['nombre' => 'X', 'codigo_iso' => str_repeat('A', 11)])
            ->assertJsonPath('errors.codigo_iso.0', 'El campo código ISO no debe tener más de 10 caracteres.');
    }

    public function test_un_nombre_repetido_en_la_papelera_avisa_en_vez_de_fallar(): void
    {
        $this->ingresar();
        $pais = Pais::create(['nombre' => 'Bolivia']);
        $this->deleteJson("/api/paises/{$pais->id}")->assertNoContent();

        $this->postJson('/api/paises', ['nombre' => 'Bolivia'])->assertUnprocessable()->assertJsonValidationErrors('nombre');
    }
}
