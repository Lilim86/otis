<?php

namespace Tests\Feature\Api;

use App\Mail\AvisoClienteMail;
use App\Mail\ComprobanteMail;
use App\Models\Cargo;
use App\Models\Cobranza;
use App\Models\Contrato;
use App\Models\CuotaContrato;
use App\Models\Edificio;
use App\Models\Empresa;
use App\Models\Personal;
use App\Models\Profesion;
use App\Models\User;
use App\Models\VisitaMantenimiento;
use App\Support\NumeroLetras;
use Database\Seeders\PermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComprobantesYExtrasApiTest extends TestCase
{
    use RefreshDatabase;

    private const FIRMA = ['ancho' => 400, 'alto' => 160, 'trazos' => [[[10, 20], [60, 80], [120, 40]], [[130, 100], [200, 90]]]];

    private Empresa $empresa;

    private Contrato $contrato;

    private CuotaContrato $cuota;

    private User $admin;

    private User $cliente;

    private Personal $tecnico;

    protected function setUp(): void
    {
        parent::setUp();
        config(['sofia.pagos.cuenta_destino' => '10000067980637', 'sofia.permisos_activos' => true]);
        Storage::fake('local');
        $this->admin = User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']);
        $this->seed(PermisosSeeder::class);

        $this->empresa = Empresa::create(['denominacion' => 'EMPRESA A', 'nit' => '123', 'tipo_empresa' => 'CLIENTE', 'correo_contacto' => 'contacto@empresa-a.com']);
        $edificio = Edificio::create(['codigo_edificio' => 'E-A', 'denominacion' => 'EDIFICIO A', 'tipo_edificacion' => 'X', 'empresa_id' => $this->empresa->id]);
        $this->contrato = Contrato::create(['codigo_contrato' => 'C-A', 'empresa_id' => $this->empresa->id, 'edificio_id' => $edificio->id, 'fecha_inicio' => today()->subDays(10)->toDateString(), 'fecha_fin' => today()->addYear()->toDateString(), 'monto_total' => 6000]);
        $this->cuota = CuotaContrato::create(['contrato_id' => $this->contrato->id, 'nro_cuota' => 1, 'fecha_cuota' => today()->addDays(2)->toDateString(), 'monto_bs' => 3000]);

        $this->cliente = User::factory()->create(['empresa_id' => $this->empresa->id]);
        $this->cliente->assignRole('Cliente');
        $this->tecnico = Personal::create(['cargo_id' => Cargo::create(['nombre' => 'TEC'])->id, 'profesion_id' => Profesion::create(['nombre' => 'ING'])->id, 'ci' => '9', 'nombres' => 'Luis', 'apellidos' => 'Paz']);
    }

    private function cobranza(array $extra = []): Cobranza
    {
        return Cobranza::create($extra + ['contrato_id' => $this->contrato->id, 'cuota_id' => $this->cuota->id, 'concepto' => 'Mantenimiento', 'monto_facturado' => 3000, 'cobrado_bs' => 3000, 'fecha_cobro' => today()->toDateString(), 'comprobante_deposito' => 'TRX-1']);
    }

    private function visita(array $extra = []): VisitaMantenimiento
    {
        return VisitaMantenimiento::create($extra + ['contrato_id' => $this->contrato->id, 'fecha_programada' => today()->addDay()->toDateString(), 'tecnico_id' => $this->tecnico->id]);
    }

    // -------------------------------------------------------------- facturas y recibos

    public function test_el_monto_se_escribe_en_letras(): void
    {
        $this->assertSame('Tres mil 50/100 bolivianos', NumeroLetras::moneda(3000.5 - 0.0 > 0 ? 3000.50 : 0));
        $this->assertSame('Veintiún mil 00/100 bolivianos', NumeroLetras::moneda(21000));
        $this->assertSame('Ciento uno 25/100 bolivianos', NumeroLetras::moneda(101.25));
        $this->assertSame('Un millón 00/100 bolivianos', NumeroLetras::moneda(1_000_000));
    }

    public function test_los_modelos_de_factura_y_recibo_salen_en_pdf(): void
    {
        Sanctum::actingAs($this->admin);
        foreach (['factura', 'recibo'] as $tipo) {
            $this->get("/api/comprobantes/modelo/{$tipo}")->assertOk()->assertHeader('content-type', 'application/pdf');
        }
        $this->get('/api/comprobantes/modelo/pagare')->assertNotFound();
    }

    public function test_factura_y_recibo_de_una_cobranza_y_sus_reglas(): void
    {
        Sanctum::actingAs($this->admin);
        $c = $this->cobranza();
        $this->get("/api/comprobantes/cobranzas/{$c->id}/factura")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get("/api/comprobantes/cobranzas/{$c->id}/recibo")->assertOk()->assertHeader('content-type', 'application/pdf');

        $sinCobrar = $this->cobranza(['cobrado_bs' => 0, 'comprobante_deposito' => null]);
        $this->getJson("/api/comprobantes/cobranzas/{$sinCobrar->id}/recibo")->assertUnprocessable();
        $this->get("/api/comprobantes/cobranzas/{$sinCobrar->id}/factura")->assertOk();

        $c->anularRegistro();
        $this->getJson("/api/comprobantes/cobranzas/{$c->id}/factura")->assertUnprocessable();
    }

    public function test_se_envia_por_correo_con_el_pdf_adjunto_y_queda_registrado(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->admin);
        $c = $this->cobranza();

        $this->postJson("/api/comprobantes/cobranzas/{$c->id}/enviar", ['tipo' => 'recibo'])->assertOk()->assertJsonPath('destinatario', 'contacto@empresa-a.com');
        Mail::assertSent(ComprobanteMail::class, fn ($m) => $m->hasTo('contacto@empresa-a.com') && count($m->attachments()) === 1);
        $this->assertDatabaseHas('envios_comprobante', ['cobranza_id' => $c->id, 'tipo' => 'RECIBO', 'resultado' => 'ENVIADO']);

        $this->postJson("/api/comprobantes/cobranzas/{$c->id}/enviar", ['tipo' => 'factura', 'correo' => 'otro@cliente.com'])->assertOk();
        Mail::assertSent(ComprobanteMail::class, fn ($m) => $m->hasTo('otro@cliente.com'));
        $this->getJson("/api/comprobantes/cobranzas/{$c->id}/envios")->assertOk()->assertJsonCount(2, 'data');

        $this->postJson("/api/comprobantes/cobranzas/{$c->id}/enviar", ['tipo' => 'recibo', 'correo' => 'no-es-correo'])->assertUnprocessable();
        $this->postJson("/api/comprobantes/cobranzas/{$c->id}/enviar", ['tipo' => 'otro'])->assertUnprocessable();

        $this->empresa->update(['correo_contacto' => null]);
        $this->postJson("/api/comprobantes/cobranzas/{$c->id}/enviar", ['tipo' => 'recibo'])->assertUnprocessable()->assertJsonValidationErrors('correo');
    }

    public function test_solo_quien_tiene_el_permiso_emite_comprobantes(): void
    {
        $c = $this->cobranza();
        $cobrador = User::factory()->create();
        $cobrador->assignRole('Cobrador');
        Sanctum::actingAs($cobrador);
        $this->getJson("/api/comprobantes/cobranzas/{$c->id}/recibo")->assertForbidden();

        $financiero = User::factory()->create();
        $financiero->assignRole('Financiero');
        Sanctum::actingAs($financiero);
        $this->get("/api/comprobantes/cobranzas/{$c->id}/recibo")->assertOk();
    }

    // -------------------------------------------------------------- pagos con comprobante

    public function test_el_cliente_sube_el_comprobante_y_el_personal_lo_ve(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->cliente);
        $datos = ['cuota_id' => $this->cuota->id, 'monto_bs' => 3000, 'fecha_pago' => today()->toDateString(), 'nro_transaccion' => 'TRX-777'];

        $this->post('/api/portal/pagos', $datos + ['comprobante' => UploadedFile::fake()->create('virus.exe', 10)], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('comprobante');
        $this->post('/api/portal/pagos', $datos + ['comprobante' => UploadedFile::fake()->create('grande.pdf', 5000, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable();

        $id = $this->post('/api/portal/pagos', $datos + ['comprobante' => UploadedFile::fake()->create('transferencia.png', 20, 'image/png')], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->get("/api/portal/pagos/{$id}/comprobante")->assertOk();
        $this->assertTrue(collect($this->getJson('/api/portal/pagos')->json('data'))->firstWhere('id', $id)['tiene_comprobante']);

        // Otro cliente no ve el comprobante ajeno.
        $otra = Empresa::create(['denominacion' => 'EMPRESA B', 'nit' => '9', 'tipo_empresa' => 'CLIENTE']);
        $otro = User::factory()->create(['empresa_id' => $otra->id]);
        $otro->assignRole('Cliente');
        Sanctum::actingAs($otro);
        $this->get("/api/portal/pagos/{$id}/comprobante")->assertNotFound();

        Sanctum::actingAs($this->admin);
        $this->get("/api/pagos-clientes/{$id}/comprobante")->assertOk();
    }

    public function test_al_confirmar_el_cliente_recibe_aviso_y_puede_bajar_su_recibo(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->cliente);
        $id = $this->postJson('/api/portal/pagos', ['cuota_id' => $this->cuota->id, 'monto_bs' => 3000, 'fecha_pago' => today()->toDateString(), 'nro_transaccion' => 'TRX-888'])->json('data.id');
        $this->get("/api/portal/pagos/{$id}/recibo", ['Accept' => 'application/json'])->assertNotFound(); // aún sin confirmar

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/pagos-clientes/{$id}/aprobar")->assertOk();
        Mail::assertSent(AvisoClienteMail::class, fn ($m) => $m->hasTo($this->cliente->email) && $m->titulo === 'Pago confirmado');

        Sanctum::actingAs($this->cliente);
        $this->get("/api/portal/pagos/{$id}/recibo")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertTrue(collect($this->getJson('/api/portal/pagos')->json('data'))->firstWhere('id', $id)['recibo_disponible']);
    }

    // -------------------------------------------------------------- firma e informe

    public function test_el_cliente_firma_la_visita_realizada_una_sola_vez_y_sale_en_el_informe(): void
    {
        Sanctum::actingAs($this->cliente);
        $v = $this->visita(['situacion' => 'REALIZADA', 'fecha_realizada' => today()->toDateString(), 'informe' => 'Todo en orden con los equipos.']);

        $firmar = fn (array $extra = []) => $this->postJson("/api/portal/mantenimientos/{$v->id}/firmar", $extra + self::FIRMA + ['firmado_por' => 'Juan Pérez']);
        $firmar(['trazos' => []])->assertUnprocessable()->assertJsonValidationErrors('trazos');
        $firmar(['trazos' => [[[1, 2]]]])->assertUnprocessable(); // un trazo necesita al menos 2 puntos
        $firmar(['trazos' => [[[1, 2], [99999, 2]]]])->assertUnprocessable(); // fuera del lienzo
        $firmar(['firmado_por' => ''])->assertUnprocessable()->assertJsonValidationErrors('firmado_por');

        $firmar()->assertOk()->assertJsonPath('data.firmada', true);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $v->fresh()->firma_cliente);
        $this->assertStringContainsString('M10 20 L60 80 L120 40', base64_decode(substr($v->fresh()->firma_cliente, strlen('data:image/svg+xml;base64,'))));
        $firmar(['firmado_por' => 'Otro'])->assertUnprocessable();

        $this->get("/api/portal/mantenimientos/{$v->id}/informe")->assertOk()->assertHeader('content-type', 'application/pdf');
        Sanctum::actingAs($this->admin);
        $this->get("/api/visitas-mantenimiento/{$v->id}/informe")->assertOk();

        Sanctum::actingAs($this->cliente);
        $pendiente = $this->visita();
        $this->postJson("/api/portal/mantenimientos/{$pendiente->id}/firmar", self::FIRMA + ['firmado_por' => 'Juan Pérez'])->assertUnprocessable();
        $this->get("/api/portal/mantenimientos/{$pendiente->id}/informe", ['Accept' => 'application/json'])->assertNotFound();
    }

    // -------------------------------------------------------------- ruta del técnico

    private function tecnicoUser(?Personal $p = null): User
    {
        $u = User::factory()->create(['personal_id' => ($p ?? $this->tecnico)->id]);
        $u->assignRole('Técnico');

        return $u;
    }

    public function test_el_tecnico_ve_solo_sus_visitas_y_las_cierra_con_informe(): void
    {
        Mail::fake();
        $mia = $this->visita(['fecha_programada' => today()->toDateString()]);
        $otroTecnico = Personal::create(['cargo_id' => $this->tecnico->cargo_id, 'profesion_id' => $this->tecnico->profesion_id, 'ci' => '10', 'nombres' => 'Ana', 'apellidos' => 'Rojas']);
        $ajena = $this->visita(['tecnico_id' => $otroTecnico->id]);
        $lejana = $this->visita(['fecha_programada' => today()->addDays(60)->toDateString()]);

        Sanctum::actingAs($this->tecnicoUser());
        $ids = collect($this->getJson('/api/tecnico/ruta')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($mia->id));
        $this->assertFalse($ids->contains($ajena->id));
        $this->assertFalse($ids->contains($lejana->id));

        $this->postJson("/api/tecnico/visitas/{$ajena->id}/completar", ['informe' => 'Informe de la visita ajena'])->assertNotFound();
        $this->postJson("/api/tecnico/visitas/{$mia->id}/completar", ['informe' => 'corto'])->assertUnprocessable();
        $this->postJson("/api/tecnico/visitas/{$mia->id}/completar", ['informe' => 'Se lubricaron guías y se revisó el freno.'])->assertOk();
        $this->assertSame('REALIZADA', $mia->fresh()->situacion);
        Mail::assertSent(AvisoClienteMail::class, fn ($m) => $m->hasTo($this->cliente->email) && $m->titulo === 'Visita realizada');
        $this->postJson("/api/tecnico/visitas/{$mia->id}/completar", ['informe' => 'Otra vez el mismo informe'])->assertUnprocessable();
    }

    public function test_quien_no_es_tecnico_no_entra_a_la_ruta(): void
    {
        Sanctum::actingAs($this->admin);
        $this->getJson('/api/tecnico/ruta')->assertForbidden();
        Sanctum::actingAs($this->cliente);
        $this->getJson('/api/tecnico/ruta')->assertForbidden();
    }

    // -------------------------------------------------------------- avisos automáticos

    public function test_los_avisos_del_dia_salen_una_sola_vez(): void
    {
        Mail::fake();
        $this->visita(['fecha_programada' => today()->addDays(2)->toDateString()]);
        $this->visita(['fecha_programada' => today()->addDays(30)->toDateString()]);
        CuotaContrato::create(['contrato_id' => $this->contrato->id, 'nro_cuota' => 2, 'fecha_cuota' => today()->subDays(3)->toDateString(), 'monto_bs' => 500]);

        $this->artisan('avisos:clientes --simular')->expectsOutputToContain('Visitas: 1')->assertSuccessful();
        Mail::assertNothingSent();

        $this->artisan('avisos:clientes')->expectsOutputToContain('Visitas: 1 · cuotas por vencer: 1 · cuotas vencidas: 1')->assertSuccessful();
        Mail::assertSent(AvisoClienteMail::class, 3);

        $this->artisan('avisos:clientes')->expectsOutputToContain('Visitas: 0 · cuotas por vencer: 0 · cuotas vencidas: 0')->assertSuccessful();
        Mail::assertSent(AvisoClienteMail::class, 3);
    }
}
