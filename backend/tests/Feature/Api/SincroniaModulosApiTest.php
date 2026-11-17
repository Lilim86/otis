<?php

namespace Tests\Feature\Api;

use App\Models\Almacen;
use App\Models\Cargo;
use App\Models\Contrato;
use App\Models\Edificio;
use App\Models\Empresa;
use App\Models\Insumo;
use App\Models\Pais;
use App\Models\Personal;
use App\Models\Profesion;
use App\Models\User;
use App\Models\VisitaMantenimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SincroniaModulosApiTest extends TestCase
{
    use RefreshDatabase;

    private Almacen $almacen;

    private Personal $tecnico;

    private User $usuarioTecnico;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']));
        $this->almacen = Almacen::create(['codigo' => '4', 'nombre' => 'ALMACEN GENERAL', 'tipo_almacen' => 'M']);
        $this->tecnico = Personal::create(['cargo_id' => $this->cargo(), 'profesion_id' => $this->profesion(), 'ci' => '123', 'nombres' => 'Pedro', 'apellidos' => 'Quispe']);
        $this->usuarioTecnico = User::create(['name' => 'Pedro', 'username' => 'pedro', 'email' => 'pedro@otis.com', 'password' => 'secret123', 'personal_id' => $this->tecnico->id]);
    }

    private function cargo(): int
    {
        return Cargo::firstOrCreate(['nombre' => 'TECNICO'])->id;
    }

    private function profesion(): int
    {
        return Profesion::firstOrCreate(['nombre' => 'ING'])->id;
    }

    private function contrato(string $s = 'A', ?int $edificioEmpresa = null): Contrato
    {
        $empresa = Empresa::create(['denominacion' => "EMPRESA {$s}", 'nit' => "NIT{$s}", 'tipo_empresa' => 'CLIENTE']);
        $edificio = Edificio::create(['codigo_edificio' => "E-{$s}", 'denominacion' => "EDIFICIO {$s}", 'tipo_edificacion' => 'X', 'empresa_id' => $edificioEmpresa ?? $empresa->id]);

        return Contrato::create(['codigo_contrato' => "C-{$s}", 'empresa_id' => $empresa->id, 'edificio_id' => $edificio->id, 'fecha_inicio' => today()->subDays(5)->toDateString(), 'fecha_fin' => today()->addYear()->toDateString(), 'monto_total' => 1000]);
    }

    public function test_el_stock_inicial_se_registra_como_ingreso_y_luego_solo_cambia_por_movimientos(): void
    {
        $r = $this->postJson('/api/insumos', ['codigo' => 'REP-1', 'descripcion' => 'POLEA', 'tipo' => 'REPUESTO', 'stock_actual' => 10, 'almacen_id' => $this->almacen->id])->assertCreated();
        $id = $r->json('data.id');

        $this->assertSame(10.0, (float) Insumo::find($id)->stock_actual);
        $this->assertDatabaseHas('movimientos_almacen', ['insumo_id' => $id, 'tipo' => 'INGRESO', 'motivo' => 'INICIAL']);

        // Se puede sacar porque el saldo del almacén existe de verdad.
        $this->postJson('/api/movimientos-almacen', ['almacen_id' => $this->almacen->id, 'insumo_id' => $id, 'tipo' => 'SALIDA', 'cantidad' => 4, 'fecha' => today()->toDateString()])->assertCreated();
        $this->assertSame(6.0, (float) Insumo::find($id)->stock_actual);

        // Editar el bien no toca el stock.
        $this->patchJson("/api/insumos/{$id}", ['stock_actual' => 999])->assertOk();
        $this->assertSame(6.0, (float) Insumo::find($id)->stock_actual);
    }

    public function test_con_stock_inicial_el_almacen_es_obligatorio(): void
    {
        $this->postJson('/api/insumos', ['codigo' => 'REP-2', 'descripcion' => 'X', 'tipo' => 'REPUESTO', 'stock_actual' => 5])
            ->assertUnprocessable()->assertJsonValidationErrors('almacen_id');
    }

    public function test_prestamo_y_devolucion_de_herramientas(): void
    {
        $h = Insumo::create(['codigo' => 'H-1', 'descripcion' => 'TALADRO', 'tipo' => 'HERRAMIENTA', 'tipo_almacen' => 'M']);
        $mov = fn (array $d) => $this->postJson('/api/movimientos-almacen', $d + ['almacen_id' => $this->almacen->id, 'insumo_id' => $h->id, 'fecha' => today()->toDateString()]);

        $mov(['tipo' => 'INGRESO', 'cantidad' => 3, 'motivo' => 'COMPRA'])->assertCreated();
        // Un préstamo exige saber quién se lleva la herramienta.
        $mov(['tipo' => 'SALIDA', 'cantidad' => 1, 'motivo' => 'PRESTAMO'])->assertUnprocessable()->assertJsonValidationErrors('personal_id');
        $mov(['tipo' => 'SALIDA', 'cantidad' => 2, 'motivo' => 'PRESTAMO', 'personal_id' => $this->tecnico->id])->assertCreated();

        $this->getJson('/api/movimientos-almacen/herramientas-en-poder')->assertOk()
            ->assertJsonPath('data.0.persona', 'Pedro Quispe')->assertJsonPath('data.0.cantidad', 2);

        // No puede devolver más de lo que tiene.
        $mov(['tipo' => 'INGRESO', 'cantidad' => 5, 'motivo' => 'DEVOLUCION', 'personal_id' => $this->tecnico->id])->assertUnprocessable()->assertJsonValidationErrors('cantidad');
        $mov(['tipo' => 'INGRESO', 'cantidad' => 2, 'motivo' => 'DEVOLUCION', 'personal_id' => $this->tecnico->id])->assertCreated();
        $this->getJson('/api/movimientos-almacen/herramientas-en-poder')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(3.0, (float) $h->fresh()->stock_actual);

        // Motivo y tipo deben coincidir.
        $mov(['tipo' => 'INGRESO', 'cantidad' => 1, 'motivo' => 'PRESTAMO', 'personal_id' => $this->tecnico->id])->assertUnprocessable()->assertJsonValidationErrors('motivo');
    }

    public function test_un_bien_de_herramientas_no_entra_a_un_almacen_de_otro_tipo(): void
    {
        $h = Insumo::create(['codigo' => 'H-2', 'descripcion' => 'LLAVE', 'tipo' => 'HERRAMIENTA', 'tipo_almacen' => 'H']);

        $this->postJson('/api/movimientos-almacen', ['almacen_id' => $this->almacen->id, 'insumo_id' => $h->id, 'tipo' => 'INGRESO', 'cantidad' => 1, 'fecha' => today()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('almacen_id');
    }

    public function test_lo_dado_de_baja_o_anulado_no_sirve_como_requisito_previo(): void
    {
        $pais = Pais::create(['nombre' => 'Bolivia', 'codigo' => 'BO']);
        $this->postJson("/api/paises/{$pais->id}/baja")->assertOk();

        $this->postJson('/api/departamentos', ['pais_id' => $pais->id, 'nombre' => 'La Paz'])->assertUnprocessable()->assertJsonValidationErrors('pais_id');
    }

    public function test_el_contrato_exige_que_el_edificio_sea_del_mismo_cliente(): void
    {
        $otra = Empresa::create(['denominacion' => 'OTRA', 'nit' => 'N2', 'tipo_empresa' => 'CLIENTE']);
        $edificio = Edificio::create(['codigo_edificio' => 'E-9', 'denominacion' => 'TORRE', 'tipo_edificacion' => 'X', 'empresa_id' => $otra->id]);
        $empresa = Empresa::create(['denominacion' => 'MIA', 'nit' => 'N1', 'tipo_empresa' => 'CLIENTE']);

        $datos = ['empresa_id' => $empresa->id, 'edificio_id' => $edificio->id, 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31', 'monto_total' => 100];
        $this->postJson('/api/contratos', $datos)->assertUnprocessable()->assertJsonValidationErrors('edificio_id');
        $this->postJson('/api/contratos', ['empresa_id' => $otra->id] + $datos)->assertCreated();
    }

    public function test_el_tecnico_no_puede_pasarse_del_maximo_de_visitas_por_dia(): void
    {
        $contrato = $this->contrato();
        $fecha = today()->addDays(3)->toDateString();
        foreach (range(1, config('sofia.mantenimiento.max_visitas_por_dia')) as $i) {
            VisitaMantenimiento::create(['contrato_id' => $contrato->id, 'nro_visita' => $i, 'tipo' => 'PREVENTIVO', 'fecha_programada' => $fecha, 'tecnico_id' => $this->tecnico->id]);
        }

        $this->postJson('/api/visitas-mantenimiento', ['contrato_id' => $contrato->id, 'fecha_programada' => $fecha, 'tecnico_id' => $this->tecnico->id])
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_programada');
        // Fuera de la vigencia del contrato tampoco.
        $this->postJson('/api/visitas-mantenimiento', ['contrato_id' => $contrato->id, 'fecha_programada' => today()->addYears(3)->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_programada');
    }

    public function test_el_tecnico_inicia_la_visita_y_al_cerrarla_se_descuenta_lo_que_uso(): void
    {
        $contrato = $this->contrato();
        $visita = VisitaMantenimiento::create(['contrato_id' => $contrato->id, 'nro_visita' => 1, 'tipo' => 'PREVENTIVO', 'fecha_programada' => today()->toDateString(), 'tecnico_id' => $this->tecnico->id]);
        $id = $this->postJson('/api/insumos', ['codigo' => 'ACE-1', 'descripcion' => 'ACEITE', 'tipo' => 'MATERIAL', 'stock_actual' => 20, 'almacen_id' => $this->almacen->id])->json('data.id');

        Sanctum::actingAs($this->usuarioTecnico);
        $this->getJson('/api/tecnico/materiales')->assertOk()->assertJsonPath('data.0.codigo', 'ACE-1')->assertJsonPath('data.0.almacenes.0.saldo', 20);
        $this->postJson("/api/tecnico/visitas/{$visita->id}/iniciar")->assertOk()->assertJsonPath('data.situacion', 'EN_CURSO');

        // Mientras está en sitio se ve en el tablero de la oficina.
        Sanctum::actingAs(User::where('username', 'admin')->first());
        $this->getJson('/api/visitas-mantenimiento/tablero')->assertOk()
            ->assertJsonPath('data.tecnicos.0.estado', 'EN_SITIO')->assertJsonPath('data.resumen.en_curso', 1);

        Sanctum::actingAs($this->usuarioTecnico);
        // Sin saldo suficiente no se cierra nada.
        $this->postJson("/api/tecnico/visitas/{$visita->id}/completar", ['informe' => 'Se lubricó todo el equipo.', 'materiales' => [['insumo_id' => $id, 'almacen_id' => $this->almacen->id, 'cantidad' => 50]]])->assertUnprocessable();
        $this->assertSame('EN_CURSO', $visita->fresh()->situacion);

        $this->postJson("/api/tecnico/visitas/{$visita->id}/completar", ['informe' => 'Se lubricó todo el equipo.', 'materiales' => [['insumo_id' => $id, 'almacen_id' => $this->almacen->id, 'cantidad' => 3]]])->assertOk();
        $this->assertSame(17.0, (float) Insumo::find($id)->stock_actual);
        $this->assertDatabaseHas('movimientos_almacen', ['visita_id' => $visita->id, 'motivo' => 'CONSUMO', 'personal_id' => $this->tecnico->id, 'tipo' => 'SALIDA']);
    }

    public function test_autoasignar_reparte_las_visitas_sin_tecnico(): void
    {
        $contrato = $this->contrato();
        $otro = Personal::create(['cargo_id' => $this->cargo(), 'profesion_id' => $this->profesion(), 'ci' => '456', 'nombres' => 'Luis', 'apellidos' => 'Mamani']);
        User::create(['name' => 'Luis', 'username' => 'luis', 'email' => 'luis@otis.com', 'password' => 'secret123', 'personal_id' => $otro->id]);
        foreach ([1, 2, 3, 4] as $n) {
            VisitaMantenimiento::create(['contrato_id' => $contrato->id, 'nro_visita' => $n, 'tipo' => 'PREVENTIVO', 'fecha_programada' => today()->addDays($n)->toDateString()]);
        }

        $this->postJson('/api/visitas-mantenimiento/autoasignar')->assertOk()->assertJsonPath('data.asignadas', 4);
        $this->assertSame(2, VisitaMantenimiento::where('tecnico_id', $this->tecnico->id)->count());
        $this->assertSame(2, VisitaMantenimiento::where('tecnico_id', $otro->id)->count());
    }

    public function test_el_panel_trae_clientes_tecnicos_y_stock_bajo(): void
    {
        $contrato = $this->contrato();
        Insumo::create(['codigo' => 'B-1', 'descripcion' => 'FUSIBLE', 'tipo' => 'REPUESTO', 'stock_actual' => 1, 'stock_minimo' => 5]);

        $this->getJson('/api/dashboard/resumen')->assertOk()
            ->assertJsonPath('data.clientes.0.cliente', 'EMPRESA A')->assertJsonPath('data.clientes.0.contratos', 1)
            ->assertJsonPath('data.stock_bajo.0.codigo', 'B-1')->assertJsonPath('data.tecnicos.0.nombre', 'Pedro Quispe');
    }
}
