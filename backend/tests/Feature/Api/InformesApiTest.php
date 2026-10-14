<?php

namespace Tests\Feature\Api;

use App\Models\Almacen;
use App\Models\Ascensor;
use App\Models\Cobranza;
use App\Models\Contrato;
use App\Models\CuotaContrato;
use App\Models\Departamento;
use App\Models\Edificio;
use App\Models\Emergencia;
use App\Models\Empresa;
use App\Models\Insumo;
use App\Models\MovimientoAlmacen;
use App\Models\Municipio;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\Provincia;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InformesApiTest extends TestCase
{
    use RefreshDatabase;

    private Departamento $lapaz;

    private Departamento $cbba;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']));
        $pais = Pais::create(['nombre' => 'Bolivia']);
        $this->lapaz = Departamento::create(['nombre' => 'LA PAZ', 'pais_id' => $pais->id]);
        $this->cbba = Departamento::create(['nombre' => 'COCHABAMBA', 'pais_id' => $pais->id]);
    }

    private function contrato(Departamento $regional, string $codigo, ?string $empresa = null): Contrato
    {
        return Contrato::create([
            'codigo_contrato' => $codigo, 'regional_id' => $regional->id,
            'empresa_id' => Empresa::create(['denominacion' => $empresa ?? "CLIENTE {$codigo}", 'nit' => $codigo, 'tipo_empresa' => 'CLIENTE'])->id,
            'edificio_id' => Edificio::create(['codigo_edificio' => "E-{$codigo}", 'denominacion' => "Edificio {$codigo}", 'tipo_edificacion' => 'X'])->id,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31', 'monto_total' => 1000,
        ]);
    }

    private function cuota(Contrato $c, int $nro, string $fecha, float $monto, string $estado = 'PENDIENTE'): CuotaContrato
    {
        return CuotaContrato::create(['contrato_id' => $c->id, 'nro_cuota' => $nro, 'fecha_cuota' => $fecha, 'monto_bs' => $monto, 'estado_cuota' => $estado]);
    }

    public function test_los_informes_exigen_sesion_y_validan_sus_parametros(): void
    {
        $this->getJson('/api/reportes/seguimiento')->assertUnprocessable()->assertJsonValidationErrors('tipo');
        $this->getJson('/api/reportes/seguimiento?tipo=magia')->assertUnprocessable();
        $this->getJson('/api/reportes/cobranzas?agrupar_por=x')->assertUnprocessable();
        $this->getJson('/api/reportes/cobranzas?agrupar_por=mes&desde=2026-05-01&hasta=2026-01-01')->assertUnprocessable()->assertJsonValidationErrors('hasta');
        $this->getJson('/api/alertas?tipo=contratos&dias=9999')->assertUnprocessable();
        $this->getJson('/api/reportes/inventario?almacen_id=999')->assertUnprocessable();
    }

    public function test_cuotas_por_facturar_solo_las_vencidas_pendientes(): void
    {
        $c = $this->contrato($this->lapaz, 'C1');
        $this->cuota($c, 1, today()->subDays(10)->toDateString(), 500);
        $this->cuota($c, 2, today()->subDays(10)->toDateString(), 300, 'FACTURADA');
        $this->cuota($c, 3, today()->addDays(30)->toDateString(), 200);
        $gestion = today()->year;

        $this->getJson("/api/reportes/seguimiento?tipo=cuotas_por_facturar&gestion={$gestion}")->assertOk()
            ->assertJsonPath('total', fn ($t) => $t <= 1)
            ->assertJsonMissing(['nro_cuota' => 2])->assertJsonMissing(['nro_cuota' => 3]);
    }

    public function test_acumulado_por_regional_y_filtro_de_regional(): void
    {
        $a = $this->contrato($this->lapaz, 'C1');
        $b = $this->contrato($this->cbba, 'C2');
        $this->cuota($a, 1, '2026-02-01', 500, 'COBRADA');
        $this->cuota($a, 2, '2026-03-01', 300, 'FACTURADA');
        $this->cuota($a, 3, '2026-04-01', 200);
        $this->cuota($b, 1, '2026-02-01', 100);

        $filas = $this->getJson('/api/reportes/seguimiento?tipo=acumulado_regional&gestion=2026')->assertOk()->json('data');
        $this->assertCount(2, $filas);
        $lp = collect($filas)->firstWhere('regional', 'LA PAZ');
        $this->assertEquals(1000, $lp['programado_bs']);
        $this->assertEquals(800, $lp['facturado_bs']);
        $this->assertEquals(500, $lp['cobrado_bs']);
        $this->assertEquals(500, $lp['pendiente_bs']);

        $this->getJson("/api/reportes/seguimiento?tipo=acumulado_regional&gestion=2026&regional_id={$this->cbba->id}")->assertJsonCount(1, 'data');
        // Otra gestión: sin datos.
        $this->getJson('/api/reportes/seguimiento?tipo=acumulado_cliente&gestion=2025')->assertJsonCount(0, 'data');
        $this->getJson('/api/reportes/seguimiento?tipo=acumulado_cliente&gestion=2026')->assertJsonCount(2, 'data');
    }

    public function test_programado_vs_ejecutado_mensual(): void
    {
        $c = $this->contrato($this->lapaz, 'C1');
        $this->cuota($c, 1, '2026-02-10', 400, 'COBRADA');
        $this->cuota($c, 2, '2026-02-20', 600);

        $filas = collect($this->getJson('/api/reportes/seguimiento?tipo=programado_vs_ejecutado&gestion=2026')->assertOk()->json('data'));
        $this->assertCount(12, $filas);
        $feb = $filas->firstWhere('mes', '2026-02');
        $this->assertEquals(1000, $feb['programado_bs']);
        $this->assertEquals(400, $feb['ejecutado_bs']);
        $this->assertEquals(40, $feb['avance_pct']);
        $this->assertNull($filas->firstWhere('mes', '2026-05')['avance_pct']);
    }

    public function test_facturas_por_cobrar_y_mora(): void
    {
        $c = $this->contrato($this->lapaz, 'C1');
        Cobranza::create(['contrato_id' => $c->id, 'concepto' => 'A', 'monto_facturado' => 500, 'fecha_cobro' => '2026-02-01', 'nro_factura' => 'F1', 'cobrado_bs' => 200]);
        Cobranza::create(['contrato_id' => $c->id, 'concepto' => 'B', 'monto_facturado' => 300, 'fecha_cobro' => '2026-02-01', 'nro_factura' => 'F2', 'cobrado_bs' => 300]);
        Cobranza::create(['contrato_id' => $c->id, 'concepto' => 'C', 'monto_facturado' => 100, 'fecha_cobro' => '2026-02-01']); // sin factura

        $this->getJson('/api/reportes/seguimiento?tipo=facturas_por_cobrar&gestion=2026')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nro_factura', 'F1')->assertJsonPath('data.0.saldo_bs', 300);
        $this->getJson('/api/reportes/seguimiento?tipo=mora_regional&gestion=2026')->assertJsonPath('data.0.saldo_bs', 300);
    }

    public function test_reporte_de_cobranzas_agrupado(): void
    {
        $a = $this->contrato($this->lapaz, 'C1');
        $b = $this->contrato($this->cbba, 'C2');
        $cobrador = Persona::create([
            'tipo_documento' => 'CI', 'numero_documento' => '1', 'primer_apellido' => 'Rojas', 'nombres' => 'Ana', 'tipo_persona' => 'COBRADOR',
            'telefono_celular' => '7', 'correo_personal' => 'a@a.com',
        ]);
        Cobranza::create(['contrato_id' => $a->id, 'concepto' => 'A', 'monto_facturado' => 500, 'cobrado_bs' => 500, 'fecha_cobro' => '2026-02-01', 'cobrador_id' => $cobrador->id]);
        Cobranza::create(['contrato_id' => $b->id, 'concepto' => 'B', 'monto_facturado' => 100, 'cobrado_bs' => 50, 'fecha_cobro' => '2026-03-01']);

        $this->getJson('/api/reportes/cobranzas?agrupar_por=regional')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/reportes/cobranzas?agrupar_por=cobrador')->assertJsonFragment(['cobrador' => 'Rojas Ana', 'cobrado_bs' => 500])->assertJsonFragment(['cobrador' => 'SIN COBRADOR']);
        $this->getJson('/api/reportes/cobranzas?agrupar_por=mes&desde=2026-03-01')->assertJsonCount(1, 'data')->assertJsonPath('data.0.mes', '2026-03');
    }

    public function test_alertas_de_contratos_repuestos_y_reparaciones(): void
    {
        $vencido = $this->contrato($this->lapaz, 'C1');
        $vencido->update(['fecha_fin' => today()->subDays(5)]);
        $porVencer = $this->contrato($this->lapaz, 'C2');
        $porVencer->update(['fecha_fin' => today()->addDays(10)]);
        $lejano = $this->contrato($this->lapaz, 'C3');
        $lejano->update(['fecha_fin' => today()->addDays(200)]);

        $filas = collect($this->getJson('/api/alertas?tipo=contratos&dias=30')->assertOk()->json('data'));
        $this->assertSame('VENCIDO', $filas->firstWhere('contrato', 'C1')['situacion']);
        $this->assertSame('POR VENCER', $filas->firstWhere('contrato', 'C2')['situacion']);
        $this->assertNull($filas->firstWhere('contrato', 'C3'));

        Insumo::create(['codigo' => 'A', 'descripcion' => 'Bajo', 'tipo' => 'REPUESTO', 'stock_actual' => 2, 'stock_minimo' => 5]);
        Insumo::create(['codigo' => 'B', 'descripcion' => 'Bien', 'tipo' => 'REPUESTO', 'stock_actual' => 9, 'stock_minimo' => 5]);
        Insumo::create(['codigo' => 'C', 'descripcion' => 'Sin mínimo', 'tipo' => 'REPUESTO', 'stock_actual' => 0, 'stock_minimo' => 0]);
        $this->getJson('/api/alertas?tipo=repuestos')->assertJsonCount(1, 'data')->assertJsonPath('data.0.codigo', 'A')->assertJsonPath('data.0.faltante', 3);

        $zona = Zona::create(['nombre' => 'Sopocachi', 'municipio_id' => Municipio::create(['nombre' => 'LP', 'provincia_id' => Provincia::create(['nombre' => 'M', 'departamento_id' => $this->lapaz->id])->id])->id]);
        $ascensor = Ascensor::create(['codigo' => 'ASC-1', 'cliente_edificio' => 'Torre', 'zona_id' => $zona->id]);
        $vieja = Emergencia::create(['ascensor_id' => $ascensor->id, 'gravedad' => 'Leve', 'descripcion_fallo' => 'x']);
        $vieja->forceFill(['created_at' => now()->subDays(40)])->save();
        Emergencia::create(['ascensor_id' => $ascensor->id, 'gravedad' => 'Leve', 'descripcion_fallo' => 'reciente']);
        $this->getJson('/api/alertas?tipo=reparaciones&dias=30')->assertJsonCount(1, 'data')->assertJsonPath('data.0.dias_abierta', 40);
    }

    public function test_exportacion_csv_para_excel(): void
    {
        $c = $this->contrato($this->lapaz, 'C1', '=HYPERLINK("http://malo")');
        $this->cuota($c, 1, '2026-02-01', 500);

        $respuesta = $this->get('/api/reportes/seguimiento?tipo=acumulado_cliente&gestion=2026&formato=csv');
        $respuesta->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', $respuesta->headers->get('content-disposition'));

        $contenido = $respuesta->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $contenido);
        $this->assertStringContainsString('cliente;cuotas;programado_bs', $contenido);
        // Un texto que empieza con "=" no debe ejecutarse como fórmula en Excel.
        $this->assertStringContainsString("'=HYPERLINK", $contenido);
    }

    public function test_reporte_de_inventario_por_almacen(): void
    {
        $almacen = Almacen::create(['codigo' => '4', 'nombre' => 'HERRAMIENTAS', 'tipo_almacen' => 'H']);
        $insumo = Insumo::create(['codigo' => 'HERR-1', 'descripcion' => 'ACEITERA', 'tipo' => 'HERRAMIENTA', 'stock_actual' => 0]);
        foreach ([['INGRESO', 10, 5], ['INGRESO', 10, 15], ['SALIDA', 5, 0]] as [$tipo, $cantidad, $costo]) {
            MovimientoAlmacen::create(['almacen_id' => $almacen->id, 'insumo_id' => $insumo->id, 'tipo' => $tipo, 'cantidad' => $cantidad, 'costo_unitario_bs' => $costo, 'fecha' => today()]);
        }

        $this->getJson("/api/reportes/inventario?almacen_id={$almacen->id}")->assertOk()
            ->assertJsonPath('data.0.cantidad_ingresada', 20)->assertJsonPath('data.0.saldo_fisico', 15)
            ->assertJsonPath('data.0.costo_promedio_bs', 10)->assertJsonPath('data.0.valor_bs', 150);
    }
}
