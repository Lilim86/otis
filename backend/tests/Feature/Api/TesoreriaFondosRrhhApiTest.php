<?php

namespace Tests\Feature\Api;

use App\Models\Banco;
use App\Models\Cargo;
use App\Models\CuentaBancaria;
use App\Models\Persona;
use App\Models\Personal;
use App\Models\Profesion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TesoreriaFondosRrhhApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']));
    }

    private function cuenta(string $numero, string $moneda = 'BOB'): CuentaBancaria
    {
        $banco = Banco::firstOrCreate(['nombre' => 'BANCO UNION']);

        return CuentaBancaria::create(['banco_id' => $banco->id, 'numero_cuenta' => $numero, 'moneda' => $moneda]);
    }

    private function persona(string $doc = '1'): Persona
    {
        return Persona::create([
            'tipo_documento' => 'CI', 'numero_documento' => $doc, 'primer_apellido' => 'Rojas', 'nombres' => 'Ana',
            'tipo_persona' => 'PERSONAL', 'telefono_celular' => '7', 'correo_personal' => 'a@a.com',
        ]);
    }

    private function empleado(string $ci = '100'): Personal
    {
        return Personal::create([
            'cargo_id' => Cargo::firstOrCreate(['nombre' => 'TECNICO'])->id, 'profesion_id' => Profesion::firstOrCreate(['nombre' => 'ING'])->id,
            'ci' => $ci, 'nombres' => 'Luis', 'apellidos' => 'Paz',
        ]);
    }

    // ----------------------------------------------------------------- tesorería

    public function test_recibo_de_ingreso_y_sus_validaciones(): void
    {
        $cuenta = $this->cuenta('111');
        $base = ['tipo' => 'RECIBO_OFICIAL', 'nro_recibo' => 'R-1', 'fecha' => '2026-03-01', 'cuenta_bancaria_id' => $cuenta->id, 'monto_bs' => 500];

        $id = $this->postJson('/api/ingresos-tesoreria', $base)->assertCreated()->assertJsonPath('data.estado_registro', 'REG')->json('data.id');
        $this->postJson('/api/ingresos-tesoreria', $base)->assertUnprocessable()->assertJsonValidationErrors('nro_recibo');
        $this->postJson('/api/ingresos-tesoreria', ['nro_recibo' => 'R-2', 'tipo' => 'OTRO'] + $base)->assertUnprocessable()->assertJsonValidationErrors('tipo');
        $this->postJson('/api/ingresos-tesoreria', ['nro_recibo' => 'R-3', 'monto_bs' => null] + $base)->assertUnprocessable()->assertJsonValidationErrors('monto_bs');
        $this->postJson('/api/ingresos-tesoreria', ['nro_recibo' => 'R-4', 'fecha' => '2999-01-01'] + $base)->assertUnprocessable()->assertJsonValidationErrors('fecha');

        $this->postJson("/api/ingresos-tesoreria/{$id}/aprobar")->assertOk();
        $this->putJson("/api/ingresos-tesoreria/{$id}", $base)->assertStatus(409); // aprobado: ya no se edita
    }

    public function test_traspaso_entre_cuentas(): void
    {
        $a = $this->cuenta('111');
        $b = $this->cuenta('222');
        $usd = $this->cuenta('333', 'USD');
        $base = ['fecha' => '2026-03-01', 'cuenta_origen_id' => $a->id, 'cuenta_destino_id' => $b->id, 'monto' => 1000];

        $this->postJson('/api/traspasos-cuentas', $base)->assertCreated()->assertJsonPath('data.cuenta_destino.numero_cuenta', '222');
        $this->postJson('/api/traspasos-cuentas', ['cuenta_destino_id' => $a->id] + $base)->assertUnprocessable()->assertJsonValidationErrors('cuenta_destino_id');
        $this->postJson('/api/traspasos-cuentas', ['cuenta_destino_id' => $usd->id] + $base)->assertUnprocessable()->assertJsonValidationErrors('cuenta_destino_id');
        $this->postJson('/api/traspasos-cuentas', ['monto' => 0] + $base)->assertUnprocessable()->assertJsonValidationErrors('monto');
    }

    public function test_conciliacion_bancaria(): void
    {
        $a = $this->cuenta('111');
        $b = $this->cuenta('222');
        $recibo = fn ($nro, $monto) => $this->postJson('/api/ingresos-tesoreria', ['tipo' => 'RECIBO_OFICIAL', 'nro_recibo' => $nro, 'fecha' => '2026-03-05', 'cuenta_bancaria_id' => $a->id, 'monto_bs' => $monto])->json('data.id');

        $this->postJson("/api/ingresos-tesoreria/{$recibo('R1', 1000)}/aprobar");
        $this->postJson("/api/ingresos-tesoreria/{$recibo('R2', 500)}/aprobar");
        $recibo('R3', 9999); // sin aprobar: no cuenta
        $traspaso = $this->postJson('/api/traspasos-cuentas', ['fecha' => '2026-03-10', 'cuenta_origen_id' => $a->id, 'cuenta_destino_id' => $b->id, 'monto' => 300])->json('data.id');
        $this->postJson("/api/traspasos-cuentas/{$traspaso}/aprobar");

        $consulta = "cuenta_bancaria_id={$a->id}&desde=2026-03-01&hasta=2026-03-31";
        $this->getJson("/api/tesoreria/conciliacion?{$consulta}&saldo_extracto=1250")->assertOk()
            ->assertJsonPath('data.ingresos', 1500)->assertJsonPath('data.traspasos_enviados', 300)
            ->assertJsonPath('data.movimiento_segun_libros', 1200)->assertJsonPath('data.diferencia', 50);
        $this->getJson("/api/tesoreria/conciliacion?cuenta_bancaria_id={$b->id}&desde=2026-03-01&hasta=2026-03-31")->assertJsonPath('data.movimiento_segun_libros', 300)
            ->assertJsonPath('data.diferencia', null);
        $this->getJson('/api/tesoreria/conciliacion')->assertUnprocessable()->assertJsonValidationErrors(['cuenta_bancaria_id', 'desde', 'hasta']);
    }

    // ---------------------------------------------------------- fondos a rendir

    public function test_un_fondo_solo_se_rinde_aprobado_y_hasta_su_saldo(): void
    {
        $responsable = $this->persona();
        $fondo = $this->postJson('/api/fondos-rendir', [
            'tipo' => 'CAJA_CHICA', 'codigo' => 'CC-1', 'responsable_id' => $responsable->id, 'fecha' => '2026-03-01', 'monto_asignado_bs' => 1000,
        ])->assertCreated()->json('data.id');
        $rendir = fn (float $monto) => $this->postJson('/api/rendiciones-fondo', ['fondo_id' => $fondo, 'fecha' => '2026-03-05', 'concepto' => 'Compra', 'monto_bs' => $monto]);

        $rendir(100)->assertUnprocessable()->assertJsonValidationErrors('fondo_id'); // aún no aprobado
        $this->postJson("/api/fondos-rendir/{$fondo}/aprobar")->assertOk();

        $rendir(700)->assertCreated();
        $rendir(400)->assertUnprocessable()->assertJsonValidationErrors('monto_bs'); // quedan 300
        $rendir(300)->assertCreated();

        $this->getJson("/api/fondos-rendir/{$fondo}")->assertJsonPath('data.total_rendido_bs', fn ($v) => (float) $v === 1000.0);
        $this->deleteJson("/api/fondos-rendir/{$fondo}")->assertStatus(409); // tiene rendiciones
    }

    public function test_los_tipos_de_fondo_se_validan(): void
    {
        $this->postJson('/api/fondos-rendir', ['tipo' => 'REGALO', 'codigo' => 'X', 'responsable_id' => 999, 'fecha' => '2026-03-01', 'monto_asignado_bs' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors(['tipo', 'responsable_id', 'monto_asignado_bs']);
    }

    // --------------------------------------------------------------------- RRHH

    public function test_planilla_calcula_liquidos_y_totales(): void
    {
        $a = $this->empleado('100');
        $b = $this->empleado('200');

        $id = $this->postJson('/api/planillas', [
            'periodo' => '2026-03',
            'detalles' => [
                ['personal_id' => $a->id, 'haber_basico_bs' => 5000, 'descuentos_bs' => 650],
                ['personal_id' => $b->id, 'haber_basico_bs' => 3000],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.total_haberes_bs', fn ($v) => (float) $v === 8000.0)
            ->assertJsonPath('data.total_descuentos_bs', fn ($v) => (float) $v === 650.0)
            ->assertJsonPath('data.total_liquido_bs', fn ($v) => (float) $v === 7350.0)
            ->assertJsonCount(2, 'data.detalles')->json('data.id');

        $this->postJson("/api/planillas/{$id}/aprobar")->assertOk()->assertJsonPath('data.estado_registro', 'APR');
        $this->putJson("/api/planillas/{$id}", ['periodo' => '2026-03'])->assertStatus(409);
    }

    public function test_validaciones_de_planilla(): void
    {
        $a = $this->empleado();
        $detalle = [['personal_id' => $a->id, 'haber_basico_bs' => 1000]];

        $this->postJson('/api/planillas', ['periodo' => '2026-13'])->assertUnprocessable()->assertJsonValidationErrors('periodo');
        $this->postJson('/api/planillas', ['periodo' => '2026-03', 'detalles' => [['personal_id' => $a->id, 'haber_basico_bs' => 100, 'descuentos_bs' => 500]]])
            ->assertUnprocessable();
        $this->assertDatabaseCount('planillas', 0); // la transacción deshizo todo

        $vacia = $this->postJson('/api/planillas', ['periodo' => '2026-04'])->assertCreated()->json('data.id');
        $this->postJson("/api/planillas/{$vacia}/aprobar")->assertUnprocessable();

        $this->postJson('/api/planillas', ['periodo' => '2026-05', 'detalles' => $detalle])->assertCreated();
        $this->postJson('/api/planillas', ['periodo' => '2026-05', 'detalles' => $detalle])->assertUnprocessable()->assertJsonValidationErrors('periodo');
    }

    public function test_contrato_de_tecnico_no_se_superpone(): void
    {
        $t = $this->empleado();
        $base = ['personal_id' => $t->id, 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-06-30', 'monto_mensual_bs' => 4000, 'objeto' => 'Mantenimiento preventivo'];

        $primero = $this->postJson('/api/contratos-tecnicos', $base)->assertCreated()->json('data.id');
        $this->postJson('/api/contratos-tecnicos', ['fecha_inicio' => '2026-06-01', 'fecha_fin' => '2026-12-31'] + $base)->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');
        $this->postJson('/api/contratos-tecnicos', ['fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-12-31'] + $base)->assertCreated();
        $this->postJson('/api/contratos-tecnicos', ['fecha_inicio' => '2027-01-01', 'fecha_fin' => '2026-01-01'] + $base)->assertUnprocessable();

        // Un contrato anulado libera el período.
        $this->postJson("/api/contratos-tecnicos/{$primero}/anular")->assertOk();
        $this->postJson('/api/contratos-tecnicos', ['fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-03-01'] + $base)->assertCreated();
    }
}
