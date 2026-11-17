<?php

namespace Tests\Feature\Api;

use App\Mail\AvisoClienteMail;
use App\Models\Cita;
use App\Models\Cobranza;
use App\Models\Contrato;
use App\Models\CuotaContrato;
use App\Models\Edificio;
use App\Models\Empresa;
use App\Models\PedidoCatalogo;
use App\Models\ProductoCatalogo;
use App\Models\User;
use App\Services\ComprobanteService;
use Database\Seeders\CatalogoAscensoresSeeder;
use Database\Seeders\PermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogoYRecibosApiTest extends TestCase
{
    use RefreshDatabase;

    private Contrato $contrato;

    private User $cliente;

    private ProductoCatalogo $producto;

    protected function setUp(): void
    {
        parent::setUp();
        // Viernes: los 3 días hábiles saltan el fin de semana y caen el miércoles siguiente.
        Carbon::setTestNow('2026-11-13 10:00:00');
        config(['sofia.permisos_activos' => true, 'sofia.gerente_correo' => 'gerente@otis.test']);
        User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']);
        $this->seed([PermisosSeeder::class, CatalogoAscensoresSeeder::class]);

        $this->contrato = $this->crearContrato('A');
        $this->cliente = User::factory()->create(['empresa_id' => $this->contrato->empresa_id]);
        $this->cliente->assignRole('Cliente');
        $this->producto = ProductoCatalogo::where('codigo', 'GEN2-MR')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function crearContrato(string $sufijo): Contrato
    {
        $empresa = Empresa::create(['denominacion' => "EMPRESA {$sufijo}", 'nit' => "NIT{$sufijo}", 'tipo_empresa' => 'CLIENTE']);
        $edificio = Edificio::create(['codigo_edificio' => "E-{$sufijo}", 'denominacion' => "EDIFICIO {$sufijo}", 'tipo_edificacion' => 'X', 'empresa_id' => $empresa->id]);

        return Contrato::create(['codigo_contrato' => "C-{$sufijo}", 'empresa_id' => $empresa->id, 'edificio_id' => $edificio->id, 'fecha_inicio' => today()->subDays(10)->toDateString(), 'fecha_fin' => today()->addYear()->toDateString(), 'monto_total' => 1000]);
    }

    private function pedir(array $datos = []): TestResponse
    {
        Sanctum::actingAs($this->cliente);

        return $this->postJson('/api/portal/pedidos', $datos + ['producto_id' => $this->producto->id, 'cantidad' => 2, 'edificio_id' => $this->contrato->edificio_id]);
    }

    public function test_el_cliente_ve_el_catalogo_con_especificaciones_y_sus_edificios(): void
    {
        Sanctum::actingAs($this->cliente);

        $r = $this->getJson('/api/portal/catalogo')->assertOk();

        $this->assertSame('2026-11-18', $r->json('fecha_estimada'));
        $this->assertSame([$this->contrato->edificio_id], array_column($r->json('edificios'), 'id'));
        $gen2 = collect($r->json('data'))->firstWhere('codigo', 'GEN2-MR');
        $this->assertContains(['etiqueta' => 'Capacidad', 'valor' => '630 a 1600 kg'], $gen2['especificaciones']);
    }

    public function test_comprar_agenda_a_tres_dias_habiles_y_avisa_al_gerente(): void
    {
        Mail::fake();

        $r = $this->pedir(['observaciones' => 'Edificio de 8 pisos'])->assertCreated();

        $this->assertSame('2026-11-18', $r->json('data.fecha_compromiso'));
        $pedido = PedidoCatalogo::findOrFail($r->json('data.id'));
        $this->assertTrue($pedido->notificado_correo);
        $this->assertSame('PEDIDO', $pedido->cita->tipo);
        $this->assertSame(config('sofia.gerente_general'), $pedido->cita->responsable);
        $this->assertSame('2026-11-18 09:00:00', $pedido->cita->fecha_inicio->toDateTimeString());
        $this->assertSame($this->contrato->edificio_id, $pedido->cita->edificio_id);
        Mail::assertSent(AvisoClienteMail::class, fn ($m) => $m->hasTo('gerente@otis.test'));
    }

    public function test_un_segundo_pedido_no_se_cruza_en_la_agenda(): void
    {
        Mail::fake();

        $this->pedir()->assertCreated();
        $segundo = $this->pedir()->assertCreated();

        $this->assertSame('2026-11-18 10:00:00', PedidoCatalogo::find($segundo->json('data.id'))->cita->fecha_inicio->toDateTimeString());
    }

    public function test_sin_correo_configurado_el_pedido_igual_queda_en_el_sistema(): void
    {
        Mail::fake();
        config(['sofia.gerente_correo' => null]);

        $r = $this->pedir()->assertCreated();

        Mail::assertNothingSent();
        $this->assertNotNull(PedidoCatalogo::find($r->json('data.id'))->cita_id);
    }

    public function test_si_falla_el_correo_el_pedido_no_se_pierde(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        $r = $this->pedir()->assertCreated();

        $this->assertFalse(PedidoCatalogo::find($r->json('data.id'))->notificado_correo);
    }

    public function test_no_se_puede_pedir_para_el_edificio_de_otro_cliente(): void
    {
        Mail::fake();
        $ajeno = $this->crearContrato('B');

        $this->pedir(['edificio_id' => $ajeno->edificio_id])->assertUnprocessable()->assertJsonValidationErrors('edificio_id');
        $this->assertSame(0, PedidoCatalogo::count());
    }

    public function test_el_cliente_solo_ve_sus_pedidos(): void
    {
        Mail::fake();
        $this->pedir()->assertCreated();
        $otro = $this->crearContrato('B');
        PedidoCatalogo::create(['producto_id' => $this->producto->id, 'empresa_id' => $otro->empresa_id, 'cantidad' => 1, 'fecha_compromiso' => '2026-11-18']);

        Sanctum::actingAs($this->cliente);

        $this->assertCount(1, $this->getJson('/api/portal/pedidos')->assertOk()->json('data'));
    }

    public function test_cancelar_el_pedido_cancela_la_cita_de_la_agenda(): void
    {
        Mail::fake();
        $id = $this->pedir()->json('data.id');

        Sanctum::actingAs(User::find(1));
        $this->putJson("/api/pedidos-catalogo/{$id}", ['situacion' => 'CANCELADO'])->assertOk();

        $this->assertSame('CANCELADA', Cita::findOrFail(PedidoCatalogo::find($id)->cita_id)->situacion);
    }

    public function test_el_cliente_no_entra_a_la_gestion_interna_de_pedidos(): void
    {
        Sanctum::actingAs($this->cliente);

        $this->getJson('/api/pedidos-catalogo')->assertForbidden();
    }

    public function test_el_recibo_muestra_el_cliente_y_el_edificio_del_contrato(): void
    {
        $cobranza = Cobranza::create(['contrato_id' => $this->contrato->id, 'concepto' => 'Mantenimiento', 'monto_facturado' => 100, 'cobrado_bs' => 100, 'fecha_cobro' => '2026-11-13']);

        $html = view('comprobantes.recibo', ['d' => app(ComprobanteService::class)->datos($cobranza, 'recibo')])->render();

        $this->assertStringContainsString('EMPRESA A', $html);
        $this->assertStringContainsString('EDIFICIO A', $html);

        Sanctum::actingAs(User::find(1));
        $this->getJson('/api/cobranzas')->assertOk()->assertJsonPath('data.0.contrato.edificio.denominacion', 'EDIFICIO A');
    }

    private function cobranzaDeCuota(string $fechaCuota, int $nro, float $cobrado, string $concepto = 'Mantenimiento trimestral'): Cobranza
    {
        $cuota = CuotaContrato::create(['contrato_id' => $this->contrato->id, 'nro_cuota' => $nro, 'fecha_cuota' => $fechaCuota, 'estado_cuota' => 'PENDIENTE', 'monto_bs' => 3000, 'concepto' => $concepto]);

        return Cobranza::create(['contrato_id' => $this->contrato->id, 'cuota_id' => $cuota->id, 'concepto' => 'Pago por transferencia ref. TRX-1', 'monto_facturado' => $cobrado, 'cobrado_bs' => $cobrado, 'fecha_cobro' => '2026-10-26', 'comprobante_deposito' => 'TRX-1']);
    }

    public function test_el_recibo_lleva_anio_completo_formato_boliviano_y_los_meses_que_cancela_la_cuota(): void
    {
        $this->cobranzaDeCuota('2026-08-12', 1, 3000);
        $cobranza = $this->cobranzaDeCuota('2026-11-12', 2, 3000);

        $datos = app(ComprobanteService::class)->datos($cobranza, 'recibo');
        $html = view('comprobantes.recibo', ['d' => $datos])->render();

        $this->assertSame([9, 10, 11], array_keys($datos['meses']));
        $this->assertSame(2026, $datos['gestion']);
        $this->assertSame('Tres mil 00/100 Bolivianos', $datos['suma_letras']);
        $this->assertStringContainsString('3.000,00', $html);
        $this->assertMatchesRegularExpression('/<td class="centro lleno">2026<\/td>/', $html);
    }

    public function test_una_cuota_que_cruza_de_anio_muestra_las_dos_gestiones(): void
    {
        $this->cobranzaDeCuota('2026-11-12', 2, 3000);
        $cobranza = $this->cobranzaDeCuota('2027-02-12', 3, 3000);

        $datos = app(ComprobanteService::class)->datos($cobranza, 'recibo');

        $this->assertEqualsCanonicalizing([12, 1, 2], array_keys($datos['meses']));
        $this->assertSame('2026-2027', $datos['gestion']);
    }

    public function test_un_pago_parcial_solo_marca_el_mes_de_la_cuota(): void
    {
        $this->cobranzaDeCuota('2026-08-12', 1, 3000);
        $datos = app(ComprobanteService::class)->datos($this->cobranzaDeCuota('2026-11-12', 2, 1000), 'recibo');

        $this->assertFalse($datos['pago_total']);
        $this->assertSame([11], array_keys($datos['meses']));
    }

    public function test_el_tipo_de_servicio_del_recibo_sale_de_la_cuota(): void
    {
        $datos = app(ComprobanteService::class)->datos($this->cobranzaDeCuota('2026-11-12', 1, 3000, 'Modernización de cabina'), 'recibo');

        $this->assertSame('MODERNIZACION', $datos['servicio']);
    }

    public function test_el_administrador_recibe_los_pedidos_pendientes_con_la_hora_de_la_reunion(): void
    {
        Mail::fake();
        $atendido = $this->pedir()->json('data.id');
        $pendiente = $this->pedir()->json('data.id');
        Sanctum::actingAs(User::find(1));
        $this->putJson("/api/pedidos-catalogo/{$atendido}", ['situacion' => 'ATENDIDO'])->assertOk();

        $r = $this->getJson('/api/pedidos-catalogo?situacion=PENDIENTE&per_page=5&ordenar_por=id&direccion=desc')->assertOk();

        $this->assertSame(1, $r->json('meta.total'));
        $this->assertSame($pendiente, $r->json('data.0.id'));
        $this->assertSame('EMPRESA A', $r->json('data.0.empresa.denominacion'));
        $this->assertSame('2026-11-18', substr($r->json('data.0.cita.fecha_inicio'), 0, 10));
    }

    public function test_los_reportes_nuevos_unen_cliente_y_edificio(): void
    {
        Mail::fake();
        $this->pedir()->assertCreated();
        Sanctum::actingAs(User::find(1));

        $this->getJson('/api/reportes/combinados/pedidos_catalogo')->assertOk()
            ->assertJsonPath('data.filas.0.cliente', 'EMPRESA A')->assertJsonPath('data.filas.0.edificio', 'EDIFICIO A');
        $this->getJson('/api/reportes/combinados/visitas_mantenimiento')->assertOk();
        $this->getJson('/api/reportes/combinados/pagos_clientes')->assertOk();
    }
}
