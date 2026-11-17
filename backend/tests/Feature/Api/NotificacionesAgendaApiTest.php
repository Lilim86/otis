<?php

namespace Tests\Feature\Api;

use App\Models\Cita;
use App\Models\User;
use Database\Seeders\PermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificacionesAgendaApiTest extends TestCase
{
    use RefreshDatabase;

    private User $gerente;

    private User $secretaria;

    protected function setUp(): void
    {
        parent::setUp();
        // 21:00 del 13/11 en La Paz: en UTC ya es el 14/11.
        Carbon::setTestNow('2026-11-14 01:00:00');
        config(['sofia.permisos_activos' => true]);
        $this->gerente = User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']);
        $this->seed(PermisosSeeder::class);
        $this->secretaria = User::factory()->create();
        $this->secretaria->assignRole('Secretaría');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function agendar(User $autor, array $extra = []): int
    {
        Sanctum::actingAs($autor);

        return $this->postJson('/api/citas', $extra + ['titulo' => 'Renovación', 'tipo' => 'REUNION', 'fecha_inicio' => '2026-11-20 09:00:00', 'fecha_fin' => '2026-11-20 10:00:00', 'con_quien' => 'Administrador'])
            ->assertCreated()->json('data.id');
    }

    private function notificaciones(): array
    {
        Sanctum::actingAs($this->gerente);

        return $this->getJson('/api/citas/notificaciones')->assertOk()->json();
    }

    public function test_la_cita_agendada_por_secretaria_le_llega_al_gerente(): void
    {
        $id = $this->agendar($this->secretaria);

        $r = $this->notificaciones();

        $this->assertSame(1, $r['total']);
        $this->assertSame($id, $r['data'][0]['id']);
        $this->assertSame('Renovación', $r['data'][0]['titulo']);
    }

    public function test_tambien_avisa_si_secretaria_la_deja_confirmada(): void
    {
        $this->agendar($this->secretaria, ['situacion' => 'CONFIRMADA']);

        $this->assertSame(1, $this->notificaciones()['total']);
    }

    public function test_una_cita_de_hoy_avisa_aunque_en_utc_ya_sea_manana(): void
    {
        $this->agendar($this->secretaria, ['fecha_inicio' => '2026-11-13 22:00:00', 'fecha_fin' => '2026-11-13 23:00:00']);

        $this->assertSame(1, $this->notificaciones()['total']);
    }

    public function test_las_citas_pasadas_canceladas_o_del_propio_gerente_no_avisan(): void
    {
        $this->agendar($this->secretaria, ['fecha_inicio' => '2026-11-10 09:00:00', 'fecha_fin' => '2026-11-10 10:00:00']);
        $this->agendar($this->secretaria, ['situacion' => 'CANCELADA']);
        $this->agendar($this->gerente, ['fecha_inicio' => '2026-11-21 09:00:00', 'fecha_fin' => '2026-11-21 10:00:00']);

        $this->assertSame(0, $this->notificaciones()['total']);
    }

    public function test_los_pedidos_del_catalogo_no_se_duplican_como_cita_nueva(): void
    {
        Sanctum::actingAs($this->secretaria);
        Cita::create(['titulo' => 'Pedido', 'tipo' => 'PEDIDO', 'responsable' => 'Gerente', 'fecha_inicio' => '2026-11-20 09:00:00', 'fecha_fin' => '2026-11-20 10:00:00', 'situacion' => 'PROGRAMADA']);

        $this->assertSame(0, $this->notificaciones()['total']);
    }

    public function test_al_verla_deja_de_avisar(): void
    {
        $id = $this->agendar($this->secretaria);

        Sanctum::actingAs($this->gerente);
        $this->postJson("/api/citas/{$id}/visto")->assertNoContent();

        $this->assertSame(0, $this->notificaciones()['total']);
    }

    public function test_secretaria_no_recibe_ni_marca_los_avisos_del_gerente(): void
    {
        $id = $this->agendar($this->secretaria);

        Sanctum::actingAs($this->secretaria);
        $this->getJson('/api/citas/notificaciones')->assertForbidden();
        $this->postJson("/api/citas/{$id}/visto")->assertForbidden();

        $this->assertNull(Cita::find($id)->vista_gerente_en);
    }
}
