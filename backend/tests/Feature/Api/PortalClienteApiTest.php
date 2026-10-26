<?php

namespace Tests\Feature\Api;

use App\Models\Contrato;
use App\Models\CuotaContrato;
use App\Models\Edificio;
use App\Models\Empresa;
use App\Models\User;
use App\Models\VisitaMantenimiento;
use App\Services\CronogramaService;
use Database\Seeders\PermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalClienteApiTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private Contrato $contrato;

    private CuotaContrato $cuota;

    private User $cliente;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sofia.pagos.cuenta_destino' => '10000067980637', 'sofia.permisos_activos' => true]);
        User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']);
        $this->seed(PermisosSeeder::class);

        [$this->empresa, $this->contrato, $this->cuota] = $this->crearContrato('A');
        $this->cliente = User::factory()->create(['empresa_id' => $this->empresa->id]);
        $this->cliente->assignRole('Cliente');
    }

    private function crearContrato(string $sufijo): array
    {
        $empresa = Empresa::create(['denominacion' => "EMPRESA {$sufijo}", 'nit' => "NIT{$sufijo}", 'tipo_empresa' => 'CLIENTE']);
        $edificio = Edificio::create(['codigo_edificio' => "E-{$sufijo}", 'denominacion' => "EDIFICIO {$sufijo}", 'tipo_edificacion' => 'X', 'empresa_id' => $empresa->id]);
        $contrato = Contrato::create(['codigo_contrato' => "C-{$sufijo}", 'empresa_id' => $empresa->id, 'edificio_id' => $edificio->id, 'fecha_inicio' => today()->subDays(10)->toDateString(), 'fecha_fin' => today()->addYear()->toDateString(), 'monto_total' => 6000]);
        $cuota = CuotaContrato::create(['contrato_id' => $contrato->id, 'nro_cuota' => 1, 'fecha_cuota' => today()->addDays(5)->toDateString(), 'monto_bs' => 3000]);

        return [$empresa, $contrato, $cuota];
    }

    private function pago(array $extra = []): array
    {
        return $extra + ['cuota_id' => $this->cuota->id, 'monto_bs' => 1000, 'fecha_pago' => today()->toDateString(), 'nro_transaccion' => 'TRX-0001', 'banco_origen' => 'Banco Unión'];
    }

    // -------------------------------------------------------------- cronograma

    public function test_el_cronograma_salta_fines_de_semana_y_feriados(): void
    {
        $servicio = app(CronogramaService::class);

        // Del viernes 2026-10-30 al lunes 2026-11-03: sábado, domingo y el 2 de noviembre (feriado) no cuentan.
        $this->assertSame('2026-11-03', $servicio->sumarDiasHabiles(Carbon::parse('2026-10-30'), 1)->toDateString());
        // 30 días hábiles son 6 semanas exactas sin feriados: del lunes 2026-03-02 al lunes 2026-04-13.
        $this->assertSame('2026-04-13', $servicio->sumarDiasHabiles(Carbon::parse('2026-03-02'), 30)->toDateString());
        $this->assertFalse($servicio->esHabil(Carbon::parse('2026-05-01')));
    }

    public function test_el_cronograma_cubre_el_contrato_cada_30_dias_habiles_y_no_se_duplica(): void
    {
        $servicio = app(CronogramaService::class);
        $primera = $servicio->generar($this->contrato);

        $this->assertGreaterThanOrEqual(6, $primera->count());
        $this->assertTrue($primera->every(fn ($v) => ! $v->fecha_programada->isWeekend() && $v->fecha_programada->lte($this->contrato->fecha_fin)));
        $this->assertSame($primera->count(), $servicio->generar($this->contrato)->count());
        $this->assertSame(1, $primera->first()->nro_visita);

        // Con una visita realizada, al regenerar se conserva y solo se rehacen las pendientes.
        $primera->first()->update(['situacion' => 'REALIZADA', 'fecha_realizada' => today()]);
        $this->assertSame($primera->count(), $servicio->generar($this->contrato, true)->count());
        $this->assertSame('REALIZADA', VisitaMantenimiento::where('contrato_id', $this->contrato->id)->orderBy('fecha_programada')->first()->situacion);
    }

    public function test_staff_genera_el_cronograma_y_el_cliente_no_puede(): void
    {
        Sanctum::actingAs(User::find(1));
        $this->postJson('/api/visitas-mantenimiento/generar', ['contrato_id' => $this->contrato->id])->assertCreated()->assertJsonStructure(['total']);
        $this->postJson('/api/visitas-mantenimiento/generar', ['contrato_id' => 999])->assertUnprocessable();

        Sanctum::actingAs($this->cliente);
        $this->postJson('/api/visitas-mantenimiento/generar', ['contrato_id' => $this->contrato->id])->assertForbidden();
        $this->getJson('/api/contratos')->assertForbidden();
    }

    // -------------------------------------------------------------- aislamiento

    public function test_el_cliente_solo_ve_lo_de_su_empresa(): void
    {
        [, $ajeno, $cuotaAjena] = $this->crearContrato('B');
        app(CronogramaService::class)->generar($this->contrato);
        app(CronogramaService::class)->generar($ajeno);
        Sanctum::actingAs($this->cliente);

        $this->getJson('/api/portal/contratos')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.codigo', 'C-A');
        $this->getJson('/api/portal/cuotas')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame([$this->contrato->id], collect($this->getJson('/api/portal/cronograma')->json('data'))->pluck('contrato_id')->unique()->all());
        $this->getJson("/api/portal/mantenimientos?contrato_id={$ajeno->id}")->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/portal/pagos', $this->pago(['cuota_id' => $cuotaAjena->id]))->assertUnprocessable();
        $this->postJson('/api/portal/mantenimientos/solicitar', ['contrato_id' => $ajeno->id, 'descripcion' => 'El ascensor hace ruido'])->assertNotFound();
    }

    public function test_un_usuario_de_la_empresa_no_entra_al_portal(): void
    {
        Sanctum::actingAs(User::find(1));
        $this->getJson('/api/portal/resumen')->assertForbidden();
    }

    // -------------------------------------------------------------- mantenimiento

    public function test_el_cliente_solicita_un_correctivo_y_califica_una_visita_realizada(): void
    {
        Sanctum::actingAs($this->cliente);

        $this->postJson('/api/portal/mantenimientos/solicitar', ['contrato_id' => $this->contrato->id, 'descripcion' => 'corto'])->assertUnprocessable();
        $id = $this->postJson('/api/portal/mantenimientos/solicitar', ['contrato_id' => $this->contrato->id, 'descripcion' => 'La puerta de cabina no cierra bien', 'urgente' => true])
            ->assertCreated()->assertJsonPath('data.situacion', 'SOLICITADA')->assertJsonPath('data.tipo', 'CORRECTIVO')->json('data.id');
        $this->assertStringStartsWith('[URGENTE]', VisitaMantenimiento::find($id)->descripcion);

        $this->postJson("/api/portal/mantenimientos/{$id}/calificar", ['calificacion' => 5])->assertUnprocessable(); // aún no se realizó
        VisitaMantenimiento::find($id)->update(['situacion' => 'REALIZADA', 'fecha_realizada' => today()]);
        $this->postJson("/api/portal/mantenimientos/{$id}/calificar", ['calificacion' => 6])->assertUnprocessable();
        $this->postJson("/api/portal/mantenimientos/{$id}/calificar", ['calificacion' => 4, 'comentario' => 'Bien'])->assertOk()->assertJsonPath('data.calificacion', 4);
        $this->getJson('/api/portal/resumen')->assertOk()->assertJsonPath('data.visitas_realizadas', 1)->assertJsonPath('data.calificacion_promedio', 4);
    }

    // -------------------------------------------------------------- pagos

    public function test_el_pago_se_valida_y_no_admite_transacciones_repetidas(): void
    {
        Sanctum::actingAs($this->cliente);

        $this->getJson('/api/portal/pagos/cuenta')->assertOk()->assertJsonPath('data.cuenta_destino', '10000067980637');
        $this->postJson('/api/portal/pagos', [])->assertUnprocessable()->assertJsonValidationErrors(['cuota_id', 'monto_bs', 'fecha_pago', 'nro_transaccion']);
        $this->postJson('/api/portal/pagos', $this->pago(['monto_bs' => 3500]))->assertUnprocessable()->assertJsonValidationErrors('monto_bs');
        $this->postJson('/api/portal/pagos', $this->pago(['fecha_pago' => today()->addDay()->toDateString()]))->assertUnprocessable()->assertJsonValidationErrors('fecha_pago');
        $this->postJson('/api/portal/pagos', $this->pago(['nro_transaccion' => 'x;DROP']))->assertUnprocessable()->assertJsonValidationErrors('nro_transaccion');

        $this->postJson('/api/portal/pagos', $this->pago())->assertCreated()->assertJsonPath('data.situacion', 'EN_VERIFICACION');
        $this->postJson('/api/portal/pagos', $this->pago(['monto_bs' => 500]))->assertUnprocessable()->assertJsonValidationErrors('nro_transaccion');

        // Lo que está en verificación ya no queda disponible para pagar otra vez.
        $this->postJson('/api/portal/pagos', $this->pago(['nro_transaccion' => 'TRX-0002', 'monto_bs' => 2500]))->assertUnprocessable()->assertJsonValidationErrors('monto_bs');
        $this->postJson('/api/portal/pagos', $this->pago(['nro_transaccion' => 'TRX-0002', 'monto_bs' => 2000]))->assertCreated();
        $this->assertEquals(0, $this->getJson('/api/portal/cuotas')->json('data.0.saldo'));
        $this->getJson('/api/portal/pagos')->assertJsonCount(2, 'data');
    }

    public function test_financiero_confirma_y_la_cuota_queda_cobrada_o_rechaza_con_motivo(): void
    {
        Sanctum::actingAs($this->cliente);
        $total = $this->postJson('/api/portal/pagos', $this->pago(['monto_bs' => 3000]))->assertCreated()->json('data.id');

        Sanctum::actingAs($this->cliente);
        $this->postJson("/api/pagos-clientes/{$total}/aprobar")->assertForbidden(); // el cliente no verifica sus pagos

        Sanctum::actingAs(User::find(1));
        $this->postJson('/api/pagos-clientes', [])->assertStatus(405);
        $this->postJson("/api/pagos-clientes/{$total}/aprobar")->assertOk()->assertJsonPath('data.estado_registro', 'APR');
        $this->assertSame('COBRADA', $this->cuota->fresh()->estado_cuota);
        $this->assertDatabaseHas('cobranzas', ['cuota_id' => $this->cuota->id, 'cobrado_bs' => 3000, 'comprobante_deposito' => 'TRX-0001']);
        $this->postJson("/api/pagos-clientes/{$total}/aprobar")->assertUnprocessable(); // no se confirma dos veces

        Sanctum::actingAs($this->cliente);
        $this->assertSame('CONFIRMADO', $this->getJson('/api/portal/pagos')->json('data.0.situacion'));

        // Un pago rechazado exige motivo y libera el saldo.
        [, , $otraCuota] = [null, null, CuotaContrato::create(['contrato_id' => $this->contrato->id, 'nro_cuota' => 2, 'fecha_cuota' => today()->addMonths(3)->toDateString(), 'monto_bs' => 3000])];
        $malo = $this->postJson('/api/portal/pagos', $this->pago(['cuota_id' => $otraCuota->id, 'nro_transaccion' => 'TRX-9999']))->json('data.id');
        Sanctum::actingAs(User::find(1));
        $this->postJson("/api/pagos-clientes/{$malo}/anular")->assertUnprocessable()->assertJsonValidationErrors('motivo_rechazo');
        $this->postJson("/api/pagos-clientes/{$malo}/anular", ['motivo_rechazo' => 'No aparece en el extracto'])->assertOk()->assertJsonPath('data.estado_registro', 'ANL');

        Sanctum::actingAs($this->cliente);
        $rechazado = collect($this->getJson('/api/portal/pagos')->json('data'))->firstWhere('nro_transaccion', 'TRX-9999');
        $this->assertSame(['RECHAZADO', 'No aparece en el extracto'], [$rechazado['situacion'], $rechazado['motivo_rechazo']]);
        $this->assertEquals(3000, collect($this->getJson('/api/portal/cuotas')->json('data'))->firstWhere('id', $otraCuota->id)['saldo']);
    }

    public function test_el_resumen_junta_lo_importante_del_cliente(): void
    {
        app(CronogramaService::class)->generar($this->contrato);
        Sanctum::actingAs($this->cliente);

        $r = $this->getJson('/api/portal/resumen')->assertOk()->json('data');
        $this->assertSame(['EMPRESA A', 1, 1], [$r['empresa']['denominacion'], $r['contratos_vigentes'], $r['cuotas_pendientes']]);
        $this->assertEquals(3000, $r['saldo_pendiente_bs']);
        $this->assertNotNull($r['proxima_visita']['fecha_programada']);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('es_cliente', true)->assertJsonPath('empresa.denominacion', 'EMPRESA A');
    }
}
