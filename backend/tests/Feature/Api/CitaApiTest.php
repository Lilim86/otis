<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CitaApiTest extends TestCase
{
    use RefreshDatabase;

    private function datos(array $extra = []): array
    {
        return $extra + [
            'titulo' => 'Renovación de contrato',
            'tipo' => 'REUNION',
            'fecha_inicio' => '2026-11-02 09:00:00',
            'fecha_fin' => '2026-11-02 10:00:00',
            'con_quien' => 'Administrador del edificio',
        ];
    }

    public function test_registra_una_cita_en_la_agenda_del_gerente(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/citas', $this->datos())->assertCreated()
            ->assertJsonPath('data.responsable', 'Ing. Mario Paredes')
            ->assertJsonPath('data.situacion', 'PROGRAMADA');
    }

    public function test_rechaza_horarios_cruzados_y_fin_antes_del_inicio(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/citas', $this->datos())->assertCreated();

        $this->postJson('/api/citas', $this->datos(['titulo' => 'Otra', 'fecha_inicio' => '2026-11-02 09:30:00', 'fecha_fin' => '2026-11-02 10:30:00']))
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');
        $this->postJson('/api/citas', $this->datos(['fecha_inicio' => '2026-11-03 10:00:00', 'fecha_fin' => '2026-11-03 09:00:00']))
            ->assertUnprocessable();
        // Pegada al final de la anterior no es cruce.
        $this->postJson('/api/citas', $this->datos(['titulo' => 'Siguiente', 'fecha_inicio' => '2026-11-02 10:00:00', 'fecha_fin' => '2026-11-02 11:00:00']))
            ->assertCreated();
    }

    public function test_una_cita_cancelada_libera_el_horario_y_se_filtra_por_fecha(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $id = $this->postJson('/api/citas', $this->datos())->json('data.id');
        $this->putJson("/api/citas/{$id}", ['situacion' => 'CANCELADA'])->assertOk();

        $this->postJson('/api/citas', $this->datos(['titulo' => 'Nueva']))->assertCreated();
        $this->getJson('/api/citas?fecha_inicio_desde=2026-11-02&fecha_inicio_hasta=2026-11-02')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/citas?situacion=CANCELADA')->assertJsonCount(1, 'data');
    }
}
