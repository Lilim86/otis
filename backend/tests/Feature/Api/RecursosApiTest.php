<?php

namespace Tests\Feature\Api;

use App\Models\Ascensor;
use App\Models\Contrato;
use App\Models\Departamento;
use App\Models\Edificio;
use App\Models\Empresa;
use App\Models\Municipio;
use App\Models\OrdenMantenimiento;
use App\Models\Pais;
use App\Models\Provincia;
use App\Models\Repuesto;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecursosApiTest extends TestCase
{
    use RefreshDatabase;

    private const RECURSOS = [
        'usuarios', 'paises', 'departamentos', 'provincias', 'municipios', 'zonas', 'vias-acceso',
        'clasificadores-tecnicos', 'mantenimientos', 'ascensores', 'emergencias', 'equipos', 'repuestos',
        'ordenes-mantenimiento', 'cargos', 'profesiones', 'personal', 'empresas', 'edificios',
        'contratos', 'insumos', 'bancos', 'cuentas-bancarias', 'cobranzas',
    ];

    private function ingresar(): User
    {
        $usuario = User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']);
        Sanctum::actingAs($usuario);

        return $usuario;
    }

    private function zona(): Zona
    {
        $pais = Pais::create(['nombre' => 'Bolivia']);
        $departamento = Departamento::create(['nombre' => 'La Paz', 'pais_id' => $pais->id]);
        $provincia = Provincia::create(['nombre' => 'Murillo', 'departamento_id' => $departamento->id]);
        $municipio = Municipio::create(['nombre' => 'La Paz', 'provincia_id' => $provincia->id]);

        return Zona::create(['nombre' => 'Sopocachi', 'municipio_id' => $municipio->id]);
    }

    public function test_todas_las_rutas_exigen_autenticacion(): void
    {
        foreach (self::RECURSOS as $recurso) {
            $this->getJson("/api/{$recurso}")->assertUnauthorized();
            $this->postJson("/api/{$recurso}", [])->assertUnauthorized();
        }

        $this->getJson('/api/dashboard/resumen')->assertUnauthorized();
        $this->getJson('/api/reportes/kardex')->assertUnauthorized();
    }

    public function test_todos_los_listados_responden(): void
    {
        $this->ingresar();

        foreach (self::RECURSOS as $recurso) {
            $this->getJson("/api/{$recurso}")->assertOk()->assertJsonStructure(['data', 'meta']);
            $this->getJson("/api/{$recurso}?all=1")->assertOk()->assertJsonStructure(['data']);
        }
    }

    public function test_crud_completo_de_paises(): void
    {
        $admin = $this->ingresar();

        $id = $this->postJson('/api/paises', ['nombre' => 'Bolivia', 'codigo_iso' => 'BO'])
            ->assertCreated()
            ->assertJsonPath('data.estado', true)
            ->assertJsonPath('data.creado_por', $admin->id)
            ->json('data.id');

        $this->postJson('/api/paises', ['nombre' => 'Bolivia'])
            ->assertUnprocessable()->assertJsonValidationErrors('nombre');

        $this->patchJson("/api/paises/{$id}", ['codigo_iso' => 'BOL'])
            ->assertOk()->assertJsonPath('data.codigo_iso', 'BOL')->assertJsonPath('data.nombre', 'Bolivia');

        $this->putJson("/api/paises/{$id}", ['nombre' => 'Bolivia', 'codigo_iso' => 'BO', 'estado' => false])
            ->assertOk()->assertJsonPath('data.estado', false);

        Pais::create(['nombre' => 'Peru']);
        $this->getJson('/api/paises?estado=1')->assertJsonCount(1, 'data');
        $this->getJson('/api/paises?q=PER')->assertJsonCount(1, 'data');

        $this->deleteJson("/api/paises/{$id}")->assertNoContent();
        $this->assertSoftDeleted('paises', ['id' => $id]);
        $this->getJson("/api/paises/{$id}")->assertNotFound();
    }

    public function test_relaciones_se_validan_y_se_devuelven(): void
    {
        $this->ingresar();
        $pais = Pais::create(['nombre' => 'Bolivia']);

        $this->postJson('/api/departamentos', ['nombre' => 'La Paz', 'pais_id' => 999])
            ->assertUnprocessable()->assertJsonValidationErrors('pais_id');

        $this->postJson('/api/departamentos', ['nombre' => 'La Paz', 'pais_id' => $pais->id])
            ->assertCreated()->assertJsonPath('data.pais.nombre', 'Bolivia');

        $this->getJson("/api/departamentos?pais_id={$pais->id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/departamentos?pais_id=999')->assertJsonCount(0, 'data');
    }

    public function test_cobranza_nueva_nace_pendiente(): void
    {
        $this->ingresar();
        $contrato = Contrato::create([
            'empresa_id' => Empresa::create(['denominacion' => 'ACME', 'nit' => '1', 'tipo_empresa' => 'CLIENTE'])->id,
            'edificio_id' => Edificio::create(['codigo_edificio' => '10101-0', 'denominacion' => 'Casa', 'tipo_edificacion' => 'Edificio'])->id,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31', 'monto_total' => 1000,
        ]);

        $this->postJson('/api/cobranzas', [
            'contrato_id' => $contrato->id, 'concepto' => '1RA CUOTA', 'monto_facturado' => 500, 'fecha_cobro' => '2026-02-01',
        ])->assertCreated()->assertJsonPath('data.estado', false)->assertJsonPath('data.contrato.empresa.denominacion', 'ACME');

        $this->postJson('/api/contratos', [
            'empresa_id' => $contrato->empresa_id, 'edificio_id' => $contrato->edificio_id,
            'fecha_inicio' => '2026-05-01', 'fecha_fin' => '2026-01-01', 'monto_total' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors('fecha_fin');
    }

    public function test_emergencia_usa_ascensor_y_nace_reportada(): void
    {
        $this->ingresar();
        $ascensor = Ascensor::create(['codigo' => 'ASC-001', 'cliente_edificio' => 'Torre', 'zona_id' => $this->zona()->id]);

        $this->postJson('/api/emergencias', [
            'ascensor_id' => $ascensor->id, 'gravedad' => 'Crítica', 'descripcion_fallo' => 'Atrapamiento',
        ])->assertCreated()
            ->assertJsonPath('data.situacion', 'Reportado')
            ->assertJsonPath('data.ascensor.zona.nombre', 'Sopocachi');

        $this->postJson('/api/emergencias', [
            'ascensor_id' => $ascensor->id, 'gravedad' => 'Rara', 'descripcion_fallo' => 'x',
        ])->assertUnprocessable()->assertJsonValidationErrors('gravedad');
    }

    public function test_finalizar_orden_descuenta_stock(): void
    {
        $this->ingresar();
        $repuesto = Repuesto::create(['codigo_parte' => 'R-1', 'nombre' => 'Cable', 'stock' => 10, 'precio' => 5, 'ubicacion_almacen' => 'A1']);

        $id = $this->postJson('/api/ordenes-mantenimiento', [
            'codigo_orden' => 'OT-1', 'equipo_ascensor' => 'ASC-1', 'fecha_programada' => '2026-10-12',
            'descripcion_trabajo' => 'Cambio de cable', 'repuesto_id' => $repuesto->id, 'cantidad_utilizada' => 4,
        ])->assertCreated()->assertJsonPath('data.situacion', 'Pendiente')->json('data.id');

        $this->putJson("/api/ordenes-mantenimiento/{$id}", [
            'codigo_orden' => 'OT-1', 'equipo_ascensor' => 'ASC-1', 'fecha_programada' => '2026-10-12',
            'descripcion_trabajo' => 'x', 'situacion' => 'Finalizado',
        ])->assertUnprocessable()->assertJsonValidationErrors('situacion');

        $this->postJson("/api/ordenes-mantenimiento/{$id}/finalizar")
            ->assertOk()->assertJsonPath('data.situacion', 'Finalizado');
        $this->assertSame(6, $repuesto->fresh()->stock);

        // Finalizar dos veces no descuenta dos veces.
        $this->postJson("/api/ordenes-mantenimiento/{$id}/finalizar")->assertOk();
        $this->assertSame(6, $repuesto->fresh()->stock);
    }

    public function test_finalizar_con_stock_insuficiente_no_cambia_nada(): void
    {
        $this->ingresar();
        $repuesto = Repuesto::create(['codigo_parte' => 'R-1', 'nombre' => 'Cable', 'stock' => 1, 'precio' => 5, 'ubicacion_almacen' => 'A1']);
        $orden = OrdenMantenimiento::create([
            'codigo_orden' => 'OT-2', 'equipo_ascensor' => 'ASC-1', 'fecha_programada' => '2026-10-12',
            'situacion' => 'Pendiente', 'descripcion_trabajo' => 'x', 'repuesto_id' => $repuesto->id, 'cantidad_utilizada' => 5,
        ]);

        $this->postJson("/api/ordenes-mantenimiento/{$orden->id}/finalizar")
            ->assertUnprocessable()->assertJsonValidationErrors('cantidad_utilizada');

        $this->assertSame('Pendiente', $orden->fresh()->situacion);
        $this->assertSame(1, $repuesto->fresh()->stock);
    }

    public function test_dashboard_resumen(): void
    {
        Http::fake(['open.er-api.com/*' => Http::response(['rates' => ['BOB' => 6.96]])]);
        $this->ingresar();
        Edificio::create(['codigo_edificio' => '10101-0', 'denominacion' => 'Casa', 'tipo_edificacion' => 'Edificio']);

        $this->getJson('/api/dashboard/resumen')->assertOk()->assertJson(['data' => [
            'total_edificios' => 1, 'total_equipos' => 0, 'total_contratos' => 0, 'monto_contratos' => 0, 'dolar_bob' => 6.96,
        ]]);
    }

    public function test_dashboard_funciona_sin_el_servicio_de_tipo_de_cambio(): void
    {
        Http::fake(['open.er-api.com/*' => Http::response([], 500)]);
        $this->ingresar();

        $this->getJson('/api/dashboard/resumen')->assertOk()->assertJsonPath('data.dolar_bob', null);
    }

    public function test_kardex_devuelve_un_pdf(): void
    {
        $this->ingresar();

        $this->get('/api/reportes/kardex', ['Accept' => 'application/pdf'])
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
