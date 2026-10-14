<?php

namespace Tests\Feature\Api;

use App\Livewire\GestionReportes;
use App\Models\Almacen;
use App\Models\Ascensor;
use App\Models\Banco;
use App\Models\Cargo;
use App\Models\Cita;
use App\Models\Cobranza;
use App\Models\Compra;
use App\Models\Contrato;
use App\Models\CuentaBancaria;
use App\Models\CuotaContrato;
use App\Models\Departamento;
use App\Models\Edificio;
use App\Models\Emergencia;
use App\Models\Empresa;
use App\Models\Equipo;
use App\Models\GrupoBien;
use App\Models\Insumo;
use App\Models\Mantenimiento;
use App\Models\MovimientoAlmacen;
use App\Models\Municipio;
use App\Models\OrdenMantenimiento;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\Personal;
use App\Models\Profesion;
use App\Models\Provincia;
use App\Models\Repuesto;
use App\Models\User;
use App\Models\ViaAcceso;
use App\Models\Zona;
use App\Services\ReporteCombinadoService;
use Database\Seeders\PermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class ReportesCombinadosApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Zona $zona;

    private Departamento $lapaz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']);
        Sanctum::actingAs($this->admin);
        $this->sembrar();
    }

    private function sembrar(): void
    {
        $pais = Pais::create(['nombre' => 'BOLIVIA', 'codigo_iso' => 'BO']);
        $this->lapaz = Departamento::create(['nombre' => 'LA PAZ', 'pais_id' => $pais->id]);
        $provincia = Provincia::create(['nombre' => 'MURILLO', 'departamento_id' => $this->lapaz->id]);
        $municipio = Municipio::create(['nombre' => 'LA PAZ', 'provincia_id' => $provincia->id]);
        $this->zona = Zona::create(['nombre' => 'SOPOCACHI', 'municipio_id' => $municipio->id]);
        ViaAcceso::create(['nombre' => 'AV. 6 DE AGOSTO', 'zona_id' => $this->zona->id]);

        // Solo la zona: el reporte debe deducir municipio, provincia, departamento y país.
        $empresa = Empresa::create(['denominacion' => 'ASOCIACION TORRES', 'nit' => '123', 'tipo_empresa' => 'CLIENTE', 'zona_id' => $this->zona->id]);
        $edificio = Edificio::create(['codigo_edificio' => '10101-0', 'denominacion' => 'TORRE AZUL', 'tipo_edificacion' => 'EDIFICIO', 'zona_id' => $this->zona->id, 'empresa_id' => $empresa->id]);
        $contrato = Contrato::create(['codigo_contrato' => 'C-1', 'empresa_id' => $empresa->id, 'edificio_id' => $edificio->id, 'regional_id' => $this->lapaz->id, 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31', 'monto_total' => 1000]);
        $cuota = CuotaContrato::create(['contrato_id' => $contrato->id, 'nro_cuota' => 1, 'fecha_cuota' => '2026-02-01', 'monto_bs' => 500, 'estado_cuota' => 'PENDIENTE']);
        $cobrador = Persona::create(['tipo_documento' => 'CI', 'numero_documento' => '9', 'primer_apellido' => 'Rojas', 'nombres' => 'Ana', 'tipo_persona' => 'PERSONAL', 'telefono_celular' => '7', 'correo_personal' => 'a@a.com']);
        $cuenta = CuentaBancaria::create(['banco_id' => Banco::create(['nombre' => 'BANCO UNION'])->id, 'numero_cuenta' => '111', 'moneda' => 'BOB']);
        Cobranza::create(['contrato_id' => $contrato->id, 'cuota_id' => $cuota->id, 'concepto' => 'Mantenimiento', 'monto_facturado' => 500, 'cobrado_bs' => 300, 'fecha_cobro' => '2026-02-10', 'cuenta_bancaria_id' => $cuenta->id, 'cobrador_id' => $cobrador->id]);

        $grupo = GrupoBien::create(['codigo' => 'H', 'nombre' => 'HERRAMIENTAS', 'nivel' => 1]);
        Equipo::create(['codigo' => 'EQ-1', 'descripcion' => 'MOTOR', 'edificio_id' => $edificio->id, 'grupo_id' => $grupo->id]);
        $almacen = Almacen::create(['codigo' => '4', 'nombre' => 'ALMACEN LA PAZ', 'tipo_almacen' => 'H', 'regional_id' => $this->lapaz->id]);
        $insumo = Insumo::create(['codigo' => 'HERR-1', 'descripcion' => 'ACEITERA', 'tipo' => 'HERRAMIENTA', 'stock_actual' => 8, 'grupo_id' => $grupo->id, 'precio_compra_bs' => 10]);
        $compra = Compra::create(['codigo_compra' => 'CP-1', 'regional_id' => $this->lapaz->id, 'almacen_id' => $almacen->id, 'fecha_compra' => '2026-03-01', 'total_bs' => 100]);
        MovimientoAlmacen::create(['almacen_id' => $almacen->id, 'insumo_id' => $insumo->id, 'tipo' => 'INGRESO', 'cantidad' => 10, 'costo_unitario_bs' => 10, 'fecha' => '2026-03-01', 'compra_id' => $compra->id]);
        MovimientoAlmacen::create(['almacen_id' => $almacen->id, 'insumo_id' => $insumo->id, 'tipo' => 'SALIDA', 'cantidad' => 2, 'costo_unitario_bs' => 10, 'fecha' => '2026-03-05']);

        Mantenimiento::create(['codigo_ascensor' => 'A-1', 'zona_id' => $this->zona->id, 'tipo' => 'PREVENTIVO', 'detalles' => 'Revisión', 'fecha_programada' => '2026-04-01']);
        $ascensor = Ascensor::create(['codigo' => 'A-1', 'cliente_edificio' => 'TORRE AZUL', 'zona_id' => $this->zona->id, 'modelo' => 'X']);
        Emergencia::create(['ascensor_id' => $ascensor->id, 'gravedad' => 'ALTA', 'descripcion_fallo' => 'Atrapados', 'situacion' => 'PENDIENTE']);
        $repuesto = Repuesto::create(['codigo_parte' => 'R-1', 'nombre' => 'POLEA', 'stock' => 3, 'precio' => 5, 'ubicacion_almacen' => 'EST-2']);
        OrdenMantenimiento::create(['codigo_orden' => 'OM-1', 'equipo_ascensor' => 'A-1', 'fecha_programada' => '2026-04-02', 'situacion' => 'Pendiente', 'descripcion_trabajo' => 'Cambio', 'repuesto_id' => $repuesto->id]);
        Personal::create(['cargo_id' => Cargo::create(['nombre' => 'TECNICO'])->id, 'profesion_id' => Profesion::create(['nombre' => 'ING'])->id, 'ci' => '100', 'nombres' => 'Luis', 'apellidos' => 'Paz']);
        Cita::create(['titulo' => 'Renovación', 'tipo' => 'REUNION', 'responsable' => 'Ing. Mario Paredes', 'fecha_inicio' => '2026-05-01 09:00', 'fecha_fin' => '2026-05-01 10:00', 'edificio_id' => $edificio->id, 'empresa_id' => $empresa->id]);
    }

    public function test_todos_los_reportes_salen_en_json_csv_y_pdf(): void
    {
        foreach (array_keys(ReporteCombinadoService::CATALOGO) as $clave) {
            $this->getJson("/api/reportes/combinados/{$clave}")->assertOk()->assertJsonPath('data.clave', $clave);
            $this->get("/api/reportes/combinados/{$clave}?formato=csv")->assertOk();
            $this->get("/api/reportes/combinados/{$clave}?formato=pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_los_reportes_traen_la_ubicacion_completa_deducida_desde_la_zona(): void
    {
        foreach (['contratos', 'edificios', 'empresas', 'equipos', 'cobranzas', 'cuotas_pendientes', 'mantenimientos', 'emergencias', 'agenda'] as $clave) {
            $fila = $this->getJson("/api/reportes/combinados/{$clave}")->json('data.filas.0');
            $this->assertSame(['BOLIVIA', 'LA PAZ', 'MURILLO', 'LA PAZ', 'SOPOCACHI'], [$fila['pais'], $fila['departamento'], $fila['provincia'], $fila['municipio'], $fila['zona']], $clave);
        }
    }

    public function test_los_reportes_combinan_las_tablas_relacionadas(): void
    {
        $contrato = $this->getJson('/api/reportes/combinados/contratos')->json('data');
        $this->assertSame('ASOCIACION TORRES', $contrato['filas'][0]['cliente']);
        $this->assertSame('TORRE AZUL', $contrato['filas'][0]['edificio']);
        $this->assertEquals(300, $contrato['filas'][0]['cobrado_bs']);
        $this->assertEquals(700, $contrato['filas'][0]['saldo_bs']);

        $cobro = $this->getJson('/api/reportes/combinados/cobranzas')->json('data.filas.0');
        $this->assertSame(['BANCO UNION', '111', 'Rojas Ana', 'TORRE AZUL'], [$cobro['banco'], $cobro['cuenta'], $cobro['cobrador'], $cobro['edificio']]);

        $kardex = $this->getJson('/api/reportes/combinados/kardex')->json('data.filas.0');
        $this->assertSame('HERRAMIENTAS', $kardex['grupo']);
        $this->assertStringContainsString('ALMACEN LA PAZ (LA PAZ, BOLIVIA): 8', $kardex['existencias']);

        $inventario = $this->getJson('/api/reportes/combinados/inventario_almacen')->json('data.filas.0');
        $this->assertSame(['BOLIVIA', 'LA PAZ'], [$inventario['pais'], $inventario['departamento']]);
        $this->assertEquals(8, $inventario['saldo']);

        $this->assertSame('POLEA', explode(' - ', $this->getJson('/api/reportes/combinados/ordenes_mantenimiento')->json('data.filas.0.repuesto'))[1]);
    }

    public function test_filtros_por_fecha_regional_y_texto_y_totales(): void
    {
        $this->assertCount(1, $this->getJson('/api/reportes/combinados/cobranzas?desde=2026-02-01&hasta=2026-02-28')->json('data.filas'));
        $this->assertCount(0, $this->getJson('/api/reportes/combinados/cobranzas?desde=2026-03-01')->json('data.filas'));
        $this->assertCount(1, $this->getJson("/api/reportes/combinados/contratos?departamento_id={$this->lapaz->id}")->json('data.filas'));
        $this->assertCount(0, $this->getJson('/api/reportes/combinados/contratos?departamento_id=999')->json('data.filas'));
        $this->assertCount(0, $this->getJson('/api/reportes/combinados/contratos?q=inexistente')->json('data.filas'));
        $this->assertEquals(300, $this->getJson('/api/reportes/combinados/cobranzas')->json('data.sumas.cobrado_bs'));
        $this->getJson('/api/reportes/combinados/cobranzas?desde=2026-05-01&hasta=2026-01-01')->assertUnprocessable();
        $this->getJson('/api/reportes/combinados/no_existe')->assertNotFound();
        $this->getJson('/api/reportes/combinados/cobranzas?formato=xml')->assertUnprocessable();
    }

    public function test_cada_rol_solo_ve_los_reportes_de_sus_modulos(): void
    {
        config(['sofia.permisos_activos' => true]);
        $this->seed(PermisosSeeder::class);

        $cobrador = User::factory()->create();
        $cobrador->assignRole('Cobrador');
        Sanctum::actingAs($cobrador);

        $this->getJson('/api/reportes/combinados')->assertOk()->assertJsonStructure(['data' => ['Financiero' => ['cobranzas']]])->assertJsonMissingPath('data.Almacenes');
        $this->getJson('/api/reportes/combinados/cobranzas')->assertOk();
        $this->getJson('/api/reportes/combinados/kardex')->assertForbidden();
        $this->getJson('/api/reportes/combinados/agenda')->assertForbidden();

        $secretaria = User::factory()->create();
        $secretaria->assignRole('Secretaría');
        Sanctum::actingAs($secretaria);
        $this->getJson('/api/reportes/combinados/agenda')->assertOk();
        $this->getJson('/api/reportes/combinados/cobranzas')->assertForbidden();
    }

    public function test_la_pantalla_de_reportes_y_la_descarga_web(): void
    {
        $this->actingAs($this->admin);

        $this->get('/reportes')->assertOk();
        $this->get('/reportes/contratos/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get('/reportes/contratos/csv')->assertOk();
        Livewire::test(GestionReportes::class)->set('clave', 'contratos')->assertSee('ASOCIACION TORRES')->assertSee('SOPOCACHI');
    }
}
