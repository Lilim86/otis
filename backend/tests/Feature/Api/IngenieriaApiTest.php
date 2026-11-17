<?php

namespace Tests\Feature\Api;

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
use App\Services\Ingenieria\PrioridadFallasService;
use App\Services\Ingenieria\RutasService;
use App\Support\Xlsx;
use Database\Seeders\PermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class IngenieriaApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Empresa $empresa;

    private Personal $tecnico;

    /** Edificios en zigzag por La Paz: centro, sur, centro, sur. */
    private const PUNTOS = [[-16.4958, -68.1336], [-16.5410, -68.0790], [-16.5090, -68.1270], [-16.5390, -68.0650]];

    protected function setUp(): void
    {
        parent::setUp();
        config(['sofia.permisos_activos' => true]);
        Storage::fake('local');
        $this->admin = User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']);
        $this->seed(PermisosSeeder::class);
        $this->empresa = Empresa::create(['denominacion' => 'EMPRESA A', 'nit' => '123', 'tipo_empresa' => 'CLIENTE']);
        $this->tecnico = Personal::create(['cargo_id' => Cargo::create(['nombre' => 'TEC'])->id, 'profesion_id' => Profesion::create(['nombre' => 'ING'])->id, 'ci' => '9', 'nombres' => 'Luis', 'apellidos' => 'Paz']);
    }

    private function contrato(int $i, array $punto): Contrato
    {
        $edificio = Edificio::create(['codigo_edificio' => "E-{$i}", 'denominacion' => "EDIFICIO {$i}", 'tipo_edificacion' => 'X', 'empresa_id' => $this->empresa->id, 'latitud' => $punto[0], 'longitud' => $punto[1]]);

        return Contrato::create(['codigo_contrato' => "C-{$i}", 'empresa_id' => $this->empresa->id, 'edificio_id' => $edificio->id, 'fecha_inicio' => today()->subMonths(6)->toDateString(), 'fecha_fin' => today()->addMonths(6)->toDateString(), 'monto_total' => 12000]);
    }

    // -------------------------------------------------------------- rutas

    public function test_la_ruta_optima_nunca_es_mas_larga_y_se_aplica(): void
    {
        foreach (self::PUNTOS as $i => $p) {
            VisitaMantenimiento::create(['contrato_id' => $this->contrato($i, $p)->id, 'fecha_programada' => today()->toDateString(), 'tecnico_id' => $this->tecnico->id]);
        }
        Sanctum::actingAs($this->admin);

        $ruta = $this->getJson('/api/ingenieria/rutas')->assertOk()->json('data.rutas.0');
        $this->assertLessThan($ruta['km_antes'], $ruta['km_despues'], 'El zigzag debe acortarse.');
        $this->assertCount(4, $ruta['optima']);

        $this->postJson('/api/ingenieria/rutas/aplicar', ['fecha' => today()->toDateString()])->assertOk();
        $this->assertSame(4, VisitaMantenimiento::whereNotNull('orden_ruta')->count());
        $this->assertDatabaseCount('optimizaciones_ruta', 1);
        $this->assertGreaterThan(0, $this->getJson('/api/ingenieria/resumen')->assertOk()->json('data.rutas_acumulado.ahorro_km'));
    }

    public function test_dos_opt_no_empeora_ninguna_ruta(): void
    {
        mt_srand(7);
        for ($caso = 0; $caso < 20; $caso++) {
            $puntos = array_map(fn () => [-16.48 - mt_rand(0, 700) / 10000, -68.06 - mt_rand(0, 800) / 10000], range(1, mt_rand(2, 7)));
            $optimo = RutasService::largo(array_map(fn ($i) => $puntos[$i], RutasService::optimizar($puntos)));
            $this->assertLessThanOrEqual(RutasService::largo($puntos) + 1e-9, $optimo);
        }
    }

    // -------------------------------------------------------------- prioridad de fallas

    public function test_el_clasificador_ordena_por_gravedad(): void
    {
        $triage = app(PrioridadFallasService::class);

        $this->assertSame('P1', $triage->clasificar('Hay una persona ATRAPADA en la cabina')['prioridad']);
        $this->assertSame('P2', $triage->clasificar('El ascensor está detenido y no funciona')['prioridad']);
        $this->assertSame('P3', $triage->clasificar('Hace ruido al subir')['prioridad']);
        $this->assertSame('P4', $triage->clasificar('Quisiera hacer una consulta sobre el contrato')['prioridad']);
        $this->assertSame('P2', $triage->clasificar('Hace ruido al subir', urgente: true)['prioridad']);
    }

    public function test_el_portal_clasifica_y_asigna_las_urgencias(): void
    {
        $contrato = $this->contrato(1, self::PUNTOS[0]);
        VisitaMantenimiento::create(['contrato_id' => $contrato->id, 'fecha_programada' => today()->toDateString(), 'tecnico_id' => $this->tecnico->id]);
        $cliente = User::factory()->create(['empresa_id' => $this->empresa->id]);
        $cliente->assignRole('Cliente');
        Sanctum::actingAs($cliente);

        $r = $this->postJson('/api/portal/mantenimientos/solicitar', ['contrato_id' => $contrato->id, 'descripcion' => 'Hay personas atrapadas entre pisos'])->assertCreated();
        $r->assertJsonPath('data.prioridad', 'P1')->assertJsonPath('data.clasificacion.objetivo_horas', 2)->assertJsonPath('data.fecha_programada', today()->toDateString());
        $this->assertSame($this->tecnico->id, VisitaMantenimiento::find($r->json('data.id'))->tecnico_id);

        $leve = $this->postJson('/api/portal/mantenimientos/solicitar', ['contrato_id' => $contrato->id, 'descripcion' => 'La luz de la cabina parpadea a veces'])->assertCreated();
        $this->assertNull(VisitaMantenimiento::find($leve->json('data.id'))->tecnico_id);
    }

    // -------------------------------------------------------------- confiabilidad y cobranza

    public function test_mas_fallas_dan_mas_riesgo_y_se_puede_adelantar(): void
    {
        $fragil = $this->contrato(1, self::PUNTOS[0]);
        $sano = $this->contrato(2, self::PUNTOS[1]);
        foreach ([$fragil, $sano] as $c) {
            VisitaMantenimiento::create(['contrato_id' => $c->id, 'tipo' => 'PREVENTIVO', 'fecha_programada' => today()->addDays(20)->toDateString()]);
        }
        foreach (range(1, 6) as $k) {
            VisitaMantenimiento::create(['contrato_id' => $fragil->id, 'tipo' => 'CORRECTIVO', 'situacion' => 'REALIZADA', 'fecha_programada' => today()->subDays($k * 20)->toDateString(), 'fecha_realizada' => today()->toDateString()]);
        }
        Sanctum::actingAs($this->admin);

        $edificios = collect($this->getJson('/api/ingenieria/confiabilidad')->assertOk()->json('data.edificios'))->keyBy('edificio_id');
        $this->assertGreaterThan($edificios[$sano->edificio_id]['probabilidad_falla'], $edificios[$fragil->edificio_id]['probabilidad_falla']);

        $this->postJson("/api/ingenieria/confiabilidad/{$fragil->edificio_id}/adelantar")->assertOk();
        $this->assertSame('REPROGRAMADA', VisitaMantenimiento::where('contrato_id', $fragil->id)->where('tipo', 'PREVENTIVO')->value('situacion'));
    }

    public function test_el_cliente_moroso_tiene_mas_riesgo_de_mora(): void
    {
        $moroso = Empresa::create(['denominacion' => 'MOROSO', 'nit' => '999', 'tipo_empresa' => 'CLIENTE']);
        $puntual = $this->contrato(1, self::PUNTOS[0]);
        $edificio = Edificio::create(['codigo_edificio' => 'E-M', 'denominacion' => 'EDIFICIO M', 'tipo_edificacion' => 'X', 'empresa_id' => $moroso->id]);
        $atrasado = Contrato::create(['codigo_contrato' => 'C-M', 'empresa_id' => $moroso->id, 'edificio_id' => $edificio->id, 'fecha_inicio' => today()->subMonths(6)->toDateString(), 'fecha_fin' => today()->addMonths(6)->toDateString(), 'monto_total' => 12000]);

        foreach ([[$puntual, 0], [$atrasado, 45]] as [$contrato, $atraso]) {
            foreach (range(1, 5) as $n) {
                $cuota = CuotaContrato::create(['contrato_id' => $contrato->id, 'nro_cuota' => $n, 'fecha_cuota' => today()->subMonths(6 - $n)->subDays(50)->toDateString(), 'monto_bs' => 1000]);
                Cobranza::create(['contrato_id' => $contrato->id, 'cuota_id' => $cuota->id, 'concepto' => 'Cuota', 'monto_facturado' => 1000, 'cobrado_bs' => 1000, 'fecha_cobro' => $cuota->fecha_cuota->copy()->addDays($atraso)->toDateString()]);
            }
        }
        Sanctum::actingAs($this->admin);

        $clientes = collect($this->getJson('/api/cobranzas/analisis')->assertOk()->assertJsonStructure(['data' => ['proyeccion' => ['semanas'], 'resumen']])->json('data.clientes'))->keyBy('empresa_id');
        $this->assertGreaterThan($clientes[$this->empresa->id]['puntaje'], $clientes[$moroso->id]['puntaje']);
        $this->assertSame('A', $clientes[$this->empresa->id]['categoria']);
    }

    public function test_sin_permiso_no_se_ve_ingenieria(): void
    {
        $usuario = User::factory()->create();
        Sanctum::actingAs($usuario);
        $this->getJson('/api/ingenieria/resumen')->assertForbidden();
        $this->getJson('/api/cobranzas/analisis')->assertForbidden();
    }

    // -------------------------------------------------------------- QR, comprobantes y Excel

    public function test_el_qr_de_cobro_lo_cambia_cobranzas_y_lo_ve_el_cliente(): void
    {
        $cliente = User::factory()->create(['empresa_id' => $this->empresa->id]);
        $cliente->assignRole('Cliente');
        Sanctum::actingAs($cliente);
        $this->getJson('/api/pagos-qr')->assertOk()->assertJsonPath('data.cuenta', '10000067980637')->assertJsonPath('data.personalizado', false);
        $this->get('/api/pagos-qr/imagen')->assertOk()->assertHeader('content-type', 'image/png');
        $this->postJson('/api/cobranzas/qr', ['banco' => 'X', 'cuenta' => '1', 'titular' => 'Y'])->assertForbidden();

        Sanctum::actingAs($this->admin);
        $datos = ['banco' => 'Banco Unión S.A.', 'cuenta' => '123-456', 'titular' => 'CGI Ltda.', 'glosa' => 'Mantenimiento'];
        $this->post('/api/cobranzas/qr', $datos + ['qr' => UploadedFile::fake()->create('qr.gif', 5, 'image/gif')], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('qr');
        $this->post('/api/cobranzas/qr', $datos + ['qr' => UploadedFile::fake()->createWithContent('qr.png', file_get_contents(resource_path('pagos/qr-cobro-prueba.png')))], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.personalizado', true);

        Sanctum::actingAs($cliente);
        $this->getJson('/api/pagos-qr')->assertJsonPath('data.cuenta', '123-456')->assertJsonPath('data.titular', 'CGI Ltda.');

        Sanctum::actingAs($this->admin);
        $this->deleteJson('/api/cobranzas/qr')->assertOk()->assertJsonPath('data.personalizado', false);
    }

    public function test_el_recibo_en_blanco_y_el_excel_de_reportes(): void
    {
        Sanctum::actingAs($this->admin);
        $this->get('/api/comprobantes/modelo/recibo')->assertOk()->assertHeader('content-type', 'application/pdf');

        $r = $this->get('/api/reportes/combinados/empresas?formato=xlsx')->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $ruta = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($ruta, $r->getContent());
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($ruta) === true);
        $this->assertStringContainsString('EMPRESA A', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        unlink($ruta);

        $this->assertSame('AA', Xlsx::col(26));
    }
}
