<?php

namespace Tests\Feature\Api;

use App\Models\Banco;
use App\Models\Cobranza;
use App\Models\Contrato;
use App\Models\CuentaBancaria;
use App\Models\CuotaContrato;
use App\Models\Edificio;
use App\Models\Empresa;
use App\Models\IngresoTesoreria;
use App\Models\Persona;
use App\Models\User;
use App\Services\ComprobanteService;
use Database\Seeders\PermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CobradorPortalApiTest extends TestCase
{
    use RefreshDatabase;

    private User $cobrador;

    private User $tesoreria;

    private CuotaContrato $cuota;

    private CuotaContrato $ajena;

    private CuentaBancaria $cuenta;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 15:00:00');
        config(['sofia.permisos_activos' => true]);
        User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']);
        $this->seed(PermisosSeeder::class);

        $persona = $this->persona('1', 'Rojas');
        $this->cobrador = User::factory()->create(['persona_id' => $persona->id]);
        $this->cobrador->assignRole('Cobrador');
        $this->tesoreria = User::factory()->create();
        $this->tesoreria->assignRole('Financiero');

        $this->cuota = $this->cuotaEn('A', $persona->id, 1500, '2026-11-01', 'Mantenimiento mensual');
        $this->ajena = $this->cuotaEn('B', $this->persona('2', 'Quispe')->id, 900, '2026-11-01');
        $this->cuenta = CuentaBancaria::create(['banco_id' => Banco::create(['nombre' => 'BANCO UNION', 'sigla' => 'BUN'])->id, 'numero_cuenta' => '111', 'moneda' => 'BOB']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function persona(string $doc, string $apellido): Persona
    {
        return Persona::create(['tipo_documento' => 'CI', 'numero_documento' => $doc, 'primer_apellido' => $apellido, 'nombres' => 'Ana', 'tipo_persona' => 'COBRADOR', 'telefono_celular' => '70000000', 'correo_personal' => "c{$doc}@example.com"]);
    }

    private function cuotaEn(string $sufijo, int $cobradorId, float $monto, string $fecha, ?string $concepto = null): CuotaContrato
    {
        $empresa = Empresa::create(['denominacion' => "EMPRESA {$sufijo}", 'nit' => "NIT{$sufijo}", 'tipo_empresa' => 'CLIENTE']);
        $edificio = Edificio::create(['codigo_edificio' => "E-{$sufijo}", 'denominacion' => "EDIFICIO {$sufijo}", 'tipo_edificacion' => 'X', 'empresa_id' => $empresa->id, 'cobrador_id' => $cobradorId, 'latitud' => -16.51, 'longitud' => -68.12]);
        $contrato = Contrato::create(['codigo_contrato' => "C-{$sufijo}", 'empresa_id' => $empresa->id, 'edificio_id' => $edificio->id, 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31', 'monto_total' => 10000]);

        return CuotaContrato::create(['contrato_id' => $contrato->id, 'nro_cuota' => 1, 'fecha_cuota' => $fecha, 'monto_bs' => $monto, 'estado_cuota' => 'PENDIENTE', 'concepto' => $concepto]);
    }

    private function cobrar(array $datos = []): TestResponse
    {
        Sanctum::actingAs($this->cobrador);

        return $this->postJson('/api/cobrador/cobros', $datos + [
            'cuota_id' => $this->cuota->id, 'tipo_cobro' => 'MANTENIMIENTO', 'metodo_pago' => 'EFECTIVO', 'monto' => 1500, 'fecha_cobro' => '2026-11-16', 'nro_recibo' => '000501',
        ]);
    }

    private function enviar(int ...$ids): TestResponse
    {
        Sanctum::actingAs($this->cobrador);

        return $this->postJson('/api/cobrador/cobros/enviar', ['ids' => $ids]);
    }

    public function test_el_cobrador_ve_solo_sus_edificios_con_lo_que_falta_cobrar(): void
    {
        Sanctum::actingAs($this->cobrador);

        $this->getJson('/api/user')->assertJsonPath('es_cobrador', true);
        $r = $this->getJson('/api/cobrador/ruta')->assertOk();

        $r->assertJsonCount(1, 'data.paradas')
            ->assertJsonPath('data.paradas.0.edificio', 'EDIFICIO A')
            ->assertJsonPath('data.paradas.0.orden', 1)
            ->assertJsonPath('data.paradas.0.cuotas.0.saldo', 1500)
            ->assertJsonPath('data.paradas.0.cuotas.0.vencida', true)
            ->assertJsonPath('data.resumen.por_cobrar', 1500);
        $this->assertGreaterThan(0, $r->json('data.resumen.km'));
    }

    public function test_un_cobrador_sin_persona_enlazada_no_entra_al_portal(): void
    {
        $sinPersona = User::factory()->create();
        $sinPersona->assignRole('Cobrador');
        Sanctum::actingAs($sinPersona);

        $this->getJson('/api/user')->assertJsonPath('es_cobrador', false);
        $this->getJson('/api/cobrador/ruta')->assertForbidden();

        Sanctum::actingAs($this->tesoreria);
        $this->getJson('/api/cobrador/ruta')->assertForbidden();
    }

    public function test_registrar_un_cobro_emite_la_factura_y_queda_por_enviar(): void
    {
        Cobranza::create(['contrato_id' => $this->ajena->contrato_id, 'concepto' => 'X', 'monto_facturado' => 1, 'fecha_cobro' => '2026-01-01', 'nro_factura' => '362']);

        $r = $this->cobrar()->assertCreated()
            ->assertJsonPath('data.situacion_tesoreria', 'PENDIENTE')
            ->assertJsonPath('data.nro_factura', '363')
            ->assertJsonPath('data.factura_a_nombre', 'EMPRESA A')
            ->assertJsonPath('data.nit_facturado', 'NITA');

        // Todavía no está pagado: eso lo confirma Tesorería.
        $this->assertFalse((bool) Cobranza::find($r->json('data.id'))->estado);
        $this->assertSame('FACTURADA', $this->cuota->fresh()->estado_cuota);
        $this->assertSame($this->cobrador->persona_id, $r->json('data.cobrador_id'));
        // Lo ya cobrado no se vuelve a mostrar en la ruta.
        $this->getJson('/api/cobrador/ruta')->assertJsonCount(0, 'data.paradas')->assertJsonCount(1, 'data.al_dia');
    }

    public function test_cada_metodo_pide_sus_datos(): void
    {
        $this->cobrar(['metodo_pago' => 'CHEQUE'])->assertUnprocessable()->assertJsonValidationErrors(['nro_cheque', 'banco_cheque']);
        $this->cobrar(['metodo_pago' => 'TRANSFERENCIA'])->assertUnprocessable()->assertJsonValidationErrors(['nro_transferencia', 'cuenta_bancaria_id']);

        $id = $this->cobrar(['metodo_pago' => 'CHEQUE', 'nro_cheque' => '0045821', 'banco_cheque' => 'BNB', 'nro_transferencia' => 'IGNORADO'])->assertCreated()->json('data.id');
        $cobranza = Cobranza::find($id);
        $this->assertNull($cobranza->comprobante_deposito);

        $forma = app(ComprobanteService::class)->datos($cobranza, 'recibo')['forma'];
        $this->assertSame(['efectivo' => false, 'cheque' => '0045821', 'banco' => 'BNB', 'transferencia' => null], $forma);
    }

    public function test_no_puede_cobrar_de_un_edificio_ajeno_ni_mas_de_lo_que_se_debe(): void
    {
        $this->cobrar(['cuota_id' => $this->ajena->id])->assertUnprocessable()->assertJsonValidationErrors('cuota_id');
        $this->cobrar(['monto' => 1500.01])->assertUnprocessable()->assertJsonValidationErrors('monto');

        $this->cobrar(['monto' => 1000])->assertCreated();
        $this->cobrar(['monto' => 600, 'nro_recibo' => '000502'])->assertUnprocessable()->assertJsonValidationErrors('monto');
        $this->cobrar(['monto' => 500, 'nro_recibo' => '000502'])->assertCreated();
    }

    public function test_enviado_a_tesoreria_ya_no_se_puede_cambiar(): void
    {
        $id = $this->cobrar()->json('data.id');

        $this->enviar($id)->assertOk()->assertJsonPath('enviados', 1);
        $this->assertSame('ENVIADO', Cobranza::find($id)->situacion_tesoreria);

        $this->putJson("/api/cobrador/cobros/{$id}", ['tipo_cobro' => 'MANTENIMIENTO', 'metodo_pago' => 'EFECTIVO', 'monto' => 100, 'fecha_cobro' => '2026-11-16'])->assertStatus(409);
        $this->deleteJson("/api/cobrador/cobros/{$id}")->assertStatus(409);
        $this->enviar($id)->assertUnprocessable();
    }

    public function test_tesoreria_aprueba_y_nace_el_ingreso_con_la_cuota_cobrada(): void
    {
        $id = $this->cobrar()->json('data.id');
        $this->enviar($id);

        Sanctum::actingAs($this->tesoreria);
        $bandeja = $this->getJson('/api/ingresos-tesoreria/cobradores')->assertOk()->assertJsonPath('por_aprobar', 1);
        $bandeja->assertJsonPath('data.0.cobrador', 'Rojas Ana')->assertJsonPath('data.0.total', 1500)->assertJsonPath('data.0.por_metodo.0.total', 1500);

        $this->postJson("/api/ingresos-tesoreria/cobradores/{$id}/aprobar", ['cuenta_bancaria_id' => $this->cuenta->id])->assertOk();

        $cobranza = Cobranza::find($id);
        $this->assertSame(['APROBADO', 'APR', true], [$cobranza->situacion_tesoreria, $cobranza->estado_registro, (bool) $cobranza->estado]);
        $this->assertSame($this->tesoreria->id, $cobranza->revisado_por);
        $this->assertSame('COBRADA', $this->cuota->fresh()->estado_cuota);

        $ingreso = IngresoTesoreria::where('cobranza_id', $id)->first();
        $this->assertSame(['RECIBO_OFICIAL', '000501', 'APR', 1500.0], [$ingreso->tipo, $ingreso->nro_recibo, $ingreso->estado_registro, (float) $ingreso->monto_bs]);

        // Ya aprobado no se aprueba dos veces.
        $this->postJson("/api/ingresos-tesoreria/cobradores/{$id}/aprobar", ['cuenta_bancaria_id' => $this->cuenta->id])->assertStatus(409);
    }

    public function test_un_pago_parcial_aprobado_no_cierra_la_cuota(): void
    {
        $id = $this->cobrar(['monto' => 700])->json('data.id');
        $this->enviar($id);

        Sanctum::actingAs($this->tesoreria);
        $this->postJson("/api/ingresos-tesoreria/cobradores/{$id}/aprobar", ['cuenta_bancaria_id' => $this->cuenta->id])->assertOk();

        $this->assertSame('FACTURADA', $this->cuota->fresh()->estado_cuota);
    }

    public function test_tesoreria_observa_y_el_cobrador_corrige_y_reenvia(): void
    {
        $id = $this->cobrar()->json('data.id');
        $this->enviar($id);

        Sanctum::actingAs($this->tesoreria);
        $this->postJson("/api/ingresos-tesoreria/cobradores/{$id}/observar", [])->assertUnprocessable()->assertJsonValidationErrors('motivo');
        $this->postJson("/api/ingresos-tesoreria/cobradores/{$id}/observar", ['motivo' => 'El número de recibo no coincide con el talonario.'])->assertOk();

        Sanctum::actingAs($this->cobrador);
        $this->getJson('/api/cobrador/cobros?situacion=OBSERVADO')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.observacion_tesoreria', 'El número de recibo no coincide con el talonario.');
        $this->putJson("/api/cobrador/cobros/{$id}", ['tipo_cobro' => 'MANTENIMIENTO', 'metodo_pago' => 'EFECTIVO', 'monto' => 1500, 'fecha_cobro' => '2026-11-16', 'nro_recibo' => '000777'])->assertOk();
        $this->enviar($id)->assertOk();

        $cobranza = Cobranza::find($id);
        $this->assertSame(['ENVIADO', '000777', null], [$cobranza->situacion_tesoreria, $cobranza->nro_recibo, $cobranza->observacion_tesoreria]);
    }

    public function test_el_cobrador_no_aprueba_ni_ve_cobros_ajenos(): void
    {
        $id = $this->cobrar()->json('data.id');
        $this->enviar($id);

        $this->getJson('/api/ingresos-tesoreria/cobradores')->assertForbidden();
        $this->postJson("/api/ingresos-tesoreria/cobradores/{$id}/aprobar", ['cuenta_bancaria_id' => $this->cuenta->id])->assertForbidden();

        $otro = User::factory()->create(['persona_id' => $this->ajena->contrato->edificio->cobrador_id]);
        $otro->assignRole('Cobrador');
        Sanctum::actingAs($otro);
        $this->get("/api/cobrador/cobros/{$id}/factura")->assertNotFound();
        $this->getJson('/api/cobrador/cobros')->assertJsonCount(0, 'data');
    }

    public function test_la_aprobacion_generica_no_se_salta_a_tesoreria(): void
    {
        $id = $this->cobrar()->json('data.id');
        $this->enviar($id);

        Sanctum::actingAs(User::find(1));
        $this->postJson("/api/cobranzas/{$id}/aprobar")->assertStatus(409);
    }

    public function test_borrar_un_cobro_por_enviar_devuelve_la_cuota_a_pendiente(): void
    {
        $id = $this->cobrar()->json('data.id');

        $this->deleteJson("/api/cobrador/cobros/{$id}")->assertNoContent();

        $this->assertSoftDeleted('cobranzas', ['id' => $id]);
        $this->assertSame('PENDIENTE', $this->cuota->fresh()->estado_cuota);
    }

    public function test_la_factura_y_el_recibo_salen_en_pdf(): void
    {
        $id = $this->cobrar(['tipo_cobro' => 'CONTRATO'])->json('data.id');

        $this->get("/api/cobrador/cobros/{$id}/factura")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get("/api/cobrador/cobros/{$id}/recibo")->assertOk()->assertHeader('content-type', 'application/pdf');

        $items = app(ComprobanteService::class)->datos(Cobranza::find($id), 'factura')['items'];
        $this->assertStringContainsString('CONTRATO Y RENOVACIÓN', $items[0]['descripcion']);
    }
}
