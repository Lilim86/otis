<?php

namespace Tests\Feature\Api;

use App\Models\Cobranza;
use App\Models\Contrato;
use App\Models\CuotaContrato;
use App\Models\Departamento;
use App\Models\Edificio;
use App\Models\Empresa;
use App\Models\GrupoBien;
use App\Models\Insumo;
use App\Models\Municipio;
use App\Models\Pais;
use App\Models\Persona;
use App\Models\Provincia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModulosSofiaApiTest extends TestCase
{
    use RefreshDatabase;

    private function ingresar(): User
    {
        $admin = User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function persona(array $extra = []): array
    {
        return $extra + [
            'tipo_documento' => 'CI', 'numero_documento' => '4567890', 'expedido_en' => 'LPZ',
            'primer_apellido' => 'Quispe', 'segundo_apellido' => 'Mamani', 'nombres' => 'Juan Carlos',
            'tipo_persona' => 'CLIENTE_INDIVIDUAL', 'telefono_celular' => '70112233', 'correo_personal' => 'juan@example.com',
        ];
    }

    private function geografia(): array
    {
        $pais = Pais::create(['nombre' => 'Bolivia']);
        $lp = Departamento::create(['nombre' => 'La Paz', 'pais_id' => $pais->id]);
        $cbb = Departamento::create(['nombre' => 'Cochabamba', 'pais_id' => $pais->id]);
        $provincia = Provincia::create(['nombre' => 'Murillo', 'departamento_id' => $lp->id]);
        $municipio = Municipio::create(['nombre' => 'La Paz', 'provincia_id' => $provincia->id]);

        return compact('pais', 'lp', 'cbb', 'provincia', 'municipio');
    }

    // ---------------------------------------------------------------- catálogos

    public function test_catalogos_para_los_combos(): void
    {
        $this->ingresar();
        $this->geografia();

        $this->getJson('/api/catalogos')->assertOk()
            ->assertJsonPath('data.estados_registro.0.codigo', 'REG')
            ->assertJsonFragment(['codigo' => 'LPZ', 'etiqueta' => 'La Paz'])
            ->assertJsonCount(2, 'data.regionales');
    }

    // ----------------------------------------------------------------- personas

    public function test_alta_de_persona_con_flujo_de_aprobacion(): void
    {
        $this->ingresar();

        $id = $this->postJson('/api/personas', $this->persona())
            ->assertCreated()
            ->assertJsonPath('data.estado_registro', 'REG')
            ->assertJsonPath('data.iniciales', 'QJ')
            ->json('data.id');

        $this->postJson("/api/personas/{$id}/aprobar")->assertOk()->assertJsonPath('data.estado_registro', 'APR');
        $this->postJson("/api/personas/{$id}/aprobar")->assertUnprocessable()->assertJsonValidationErrors('estado_registro');

        $this->getJson('/api/personas?estado_registro=APR')->assertJsonCount(1, 'data');
        $this->getJson('/api/personas?estado_registro=REG')->assertJsonCount(0, 'data');

        $this->postJson("/api/personas/{$id}/anular")->assertOk()->assertJsonPath('data.estado_registro', 'ANL');
        $this->putJson("/api/personas/{$id}", $this->persona())->assertStatus(409);
        $this->postJson("/api/personas/{$id}/anular")->assertUnprocessable();
        // Anular no es eliminar: sigue visible y no está en la papelera.
        $this->getJson('/api/personas')->assertJsonCount(1, 'data');
        $this->getJson('/api/personas/papelera')->assertJsonCount(0, 'data');
    }

    public function test_estado_registro_no_se_puede_forzar_por_la_api(): void
    {
        $this->ingresar();

        $this->postJson('/api/personas', $this->persona(['estado_registro' => 'APR']))
            ->assertCreated()->assertJsonPath('data.estado_registro', 'REG');
    }

    public function test_validaciones_de_persona(): void
    {
        $this->ingresar();
        Persona::create($this->persona());

        $this->postJson('/api/personas', [])->assertUnprocessable()->assertJsonValidationErrors(
            ['tipo_documento', 'numero_documento', 'primer_apellido', 'nombres', 'tipo_persona', 'telefono_celular', 'correo_personal']
        );
        $this->postJson('/api/personas', $this->persona())->assertUnprocessable()->assertJsonValidationErrors('numero_documento');
        $this->postJson('/api/personas', $this->persona(['numero_documento' => '1', 'expedido_en' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('expedido_en');
        $this->postJson('/api/personas', $this->persona(['numero_documento' => '2', 'tipo_persona' => 'MARCIANO']))
            ->assertUnprocessable()->assertJsonValidationErrors('tipo_persona');
        $this->postJson('/api/personas', $this->persona(['numero_documento' => '3', 'correo_personal' => 'no-es-correo']))
            ->assertUnprocessable()->assertJsonValidationErrors('correo_personal');
        $this->postJson('/api/personas', $this->persona(['numero_documento' => '4', 'fecha_nacimiento' => '2999-01-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('fecha_nacimiento');
    }

    public function test_la_ubicacion_respeta_la_jerarquia_geografica(): void
    {
        $this->ingresar();
        $g = $this->geografia();

        $this->postJson('/api/personas', $this->persona(['departamento_id' => $g['lp']->id, 'provincia_id' => $g['provincia']->id, 'municipio_id' => $g['municipio']->id]))
            ->assertCreated()->assertJsonPath('data.municipio.nombre', 'La Paz');

        // Murillo no pertenece a Cochabamba.
        $this->postJson('/api/personas', $this->persona(['numero_documento' => '9', 'departamento_id' => $g['cbb']->id, 'provincia_id' => $g['provincia']->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('provincia_id');
    }

    // ------------------------------------------------------------------ empresas

    public function test_empresa_con_datos_completos(): void
    {
        $this->ingresar();
        $g = $this->geografia();

        $this->postJson('/api/empresas', [
            'tipo_empresa' => 'EMPRESA - CLIENTE', 'tipo_documento' => 'NIT', 'nit' => '510968023', 'expedido_en' => 'LPZ',
            'iniciales_sucursal' => 'ACE', 'denominacion' => 'ASOCIACION DE COPROPIETARIOS', 'fecha_creacion' => '2000-01-01',
            'telefono_celular' => '79545825', 'correo_contacto' => 'edificio@gmail.com', 'pagina_web' => 'https://ejemplo.bo',
            'pais_id' => $g['pais']->id, 'departamento_id' => $g['lp']->id,
        ])->assertCreated()->assertJsonPath('data.estado_registro', 'REG')->assertJsonPath('data.departamento.nombre', 'La Paz');

        $this->postJson('/api/empresas', ['tipo_empresa' => 'X', 'nit' => '1', 'denominacion' => 'Y', 'pagina_web' => 'no-url', 'correo_contacto' => 'mal'])
            ->assertUnprocessable()->assertJsonValidationErrors(['pagina_web', 'correo_contacto']);
    }

    // ----------------------------------------------------------------- edificios

    public function test_edificio_con_ubicacion_contactos_y_georreferencia(): void
    {
        $this->ingresar();
        $propietario = Persona::create($this->persona());

        $this->postJson('/api/edificios', [
            'codigo_edificio' => '20101-1222', 'codigo_administrativo' => '1222', 'denominacion' => 'LOS JARDINES',
            'tipo_edificacion' => 'EDIFICIO MIXTO', 'referencia_cercana' => '6 DE AGOSTO Y PEDRO SALAZAR',
            'propietario_id' => $propietario->id, 'contacto_cobranzas_id' => $propietario->id,
            'latitud' => -16.5, 'longitud' => -68.15, 'altura_msnm' => 3640,
        ])->assertCreated()->assertJsonPath('data.propietario.nombres', 'Juan Carlos');

        $this->postJson('/api/edificios', ['codigo_edificio' => 'X', 'denominacion' => 'Y', 'tipo_edificacion' => 'Z', 'latitud' => 200, 'longitud' => -200])
            ->assertUnprocessable()->assertJsonValidationErrors(['latitud', 'longitud']);
    }

    // -------------------------------------------------------------- grupos/bienes

    public function test_clasificador_de_grupos_valida_los_niveles(): void
    {
        $this->ingresar();

        $grupo = $this->postJson('/api/grupos-bienes', ['codigo' => '40000', 'nombre' => 'EQUIPOS', 'nivel' => 1])->assertCreated()->json('data.id');
        $sub1 = $this->postJson('/api/grupos-bienes', ['codigo' => '43000', 'nombre' => 'ELEVACION', 'nivel' => 2, 'padre_id' => $grupo])->assertCreated()->json('data.id');

        $this->postJson('/api/grupos-bienes', ['codigo' => '43340', 'nombre' => 'Equipo', 'nivel' => 3, 'padre_id' => $grupo])
            ->assertUnprocessable()->assertJsonValidationErrors('padre_id'); // el padre de un nivel 3 es un nivel 2
        $this->postJson('/api/grupos-bienes', ['codigo' => '50000', 'nombre' => 'X', 'nivel' => 1, 'padre_id' => $grupo])
            ->assertUnprocessable()->assertJsonValidationErrors('padre_id');
        $this->postJson('/api/grupos-bienes', ['codigo' => '43500', 'nombre' => 'X', 'nivel' => 2])
            ->assertUnprocessable()->assertJsonValidationErrors('padre_id');

        $this->deleteJson("/api/grupos-bienes/{$grupo}")->assertStatus(409);
        $this->assertNotNull($sub1);
    }

    public function test_equipo_con_clasificacion_precios_e_insumos_basicos(): void
    {
        $this->ingresar();
        $g1 = GrupoBien::create(['codigo' => '40000', 'nombre' => 'EQUIPOS', 'nivel' => 1]);
        $g2 = GrupoBien::create(['codigo' => '43000', 'nombre' => 'ELEVACION', 'nivel' => 2, 'padre_id' => $g1->id]);
        $g3 = GrupoBien::create(['codigo' => '43340', 'nombre' => 'Equipo de Elevación', 'nivel' => 3, 'padre_id' => $g2->id]);
        $grasa = Insumo::create(['codigo' => 'MAT-1', 'descripcion' => 'GRASA RODAM', 'tipo' => 'MATERIAL', 'stock_actual' => 5]);
        $trapo = Insumo::create(['codigo' => 'MAT-2', 'descripcion' => 'TRAPO', 'tipo' => 'MATERIAL', 'stock_actual' => 5]);

        $datos = [
            'codigo' => '00N00000', 'descripcion' => 'TEST', 'grupo_id' => $g1->id, 'subgrupo1_id' => $g2->id, 'subgrupo2_id' => $g3->id,
            'unidad_medida' => 'EQP', 'industria' => 'BRA', 'marca' => 'WECO', 'modelo' => '506-NCE', 'rotacion' => 'PROMEDIO',
            'precio_compra_fob_bs' => 10, 'precio_compra_fob_usd' => 1.44, 'horas_servicio' => 2,
            'insumos_basicos' => [['insumo_id' => $trapo->id, 'cantidad' => 1], ['insumo_id' => $grasa->id, 'cantidad' => 2]],
        ];

        $id = $this->postJson('/api/equipos', $datos)->assertCreated()
            ->assertJsonPath('data.subgrupo2.codigo', '43340')
            ->assertJsonCount(2, 'data.insumos_basicos')
            ->assertJsonPath('data.estado_registro', 'REG')
            ->json('data.id');

        // Modificar reemplaza la lista; si no se envía, se conserva.
        $this->patchJson("/api/equipos/{$id}", ['insumos_basicos' => [['insumo_id' => $grasa->id, 'cantidad' => 3]]])
            ->assertOk()->assertJsonCount(1, 'data.insumos_basicos')
            ->assertJsonPath('data.insumos_basicos.0.pivot.cantidad', fn ($v) => (float) $v === 3.0);
        $this->patchJson("/api/equipos/{$id}", ['marca' => 'OTIS'])->assertOk()->assertJsonCount(1, 'data.insumos_basicos');

        // El subgrupo 2 debe colgar del subgrupo 1 elegido.
        $otro = GrupoBien::create(['codigo' => '43999', 'nombre' => 'Otro', 'nivel' => 2, 'padre_id' => $g1->id]);
        $this->postJson('/api/equipos', ['codigo' => 'E2', 'descripcion' => 'X', 'subgrupo1_id' => $otro->id, 'subgrupo2_id' => $g3->id])
            ->assertUnprocessable()->assertJsonValidationErrors('subgrupo2_id');
        $this->postJson('/api/equipos', ['codigo' => 'E3', 'descripcion' => 'X', 'insumos_basicos' => [['insumo_id' => $grasa->id, 'cantidad' => 0]]])
            ->assertUnprocessable();
        $this->postJson('/api/equipos', ['codigo' => 'E4', 'descripcion' => 'X', 'insumos_basicos' => [['insumo_id' => $grasa->id, 'cantidad' => 1], ['insumo_id' => $grasa->id, 'cantidad' => 1]]])
            ->assertUnprocessable();
    }

    public function test_calculo_de_precios_de_venta(): void
    {
        $this->ingresar();

        $this->postJson('/api/equipos/calcular-precios', ['precio_compra_fob_bs' => 10, 'precio_compra_fob_usd' => 1.44])
            ->assertOk()
            ->assertJsonPath('data.precio_venta_base_bs', 18.29)
            ->assertJsonPath('data.precio_venta_cliente_bs', 21.39)
            ->assertJsonPath('data.precio_venta_base_usd', 2.63)
            ->assertJsonPath('data.precio_venta_cliente_usd', 3.08);

        // Con un solo precio, el otro se deduce con el tipo de cambio.
        $this->postJson('/api/equipos/calcular-precios', ['precio_compra_fob_bs' => 69.6])->assertOk()->assertJsonPath('data.precio_compra_fob_usd', 10);
        $this->postJson('/api/equipos/calcular-precios', [])->assertUnprocessable();
    }

    public function test_bien_o_servicio_con_precios_y_codigo_sin(): void
    {
        $this->ingresar();

        $this->postJson('/api/insumos', [
            'codigo' => 'SERV-0003', 'descripcion' => 'SUELDOS Y SALARIOS', 'tipo' => 'SERVICIO', 'unidad_medida' => 'SER',
            'industria' => 'BOL', 'rotacion' => 'NO_APROBADO', 'stock_actual' => 0, 'stock_minimo' => 0, 'tipo_almacen' => 'A',
            'codigo_sin' => '99793', 'descripcion_sin' => 'OTROS PRODUCTOS O SERVICIOS ALCANZADOS POR EL IVA',
        ])->assertCreated()->assertJsonPath('data.estado_registro', 'REG');

        $this->postJson('/api/insumos', ['codigo' => 'X', 'descripcion' => 'Y', 'tipo' => 'T', 'stock_actual' => 0, 'tipo_almacen' => 'Z', 'rotacion' => 'RARO'])
            ->assertUnprocessable()->assertJsonValidationErrors(['tipo_almacen', 'rotacion']);
    }

    // ------------------------------------------------- contratos, cuotas, cobranzas

    private function contrato(string $codigoEdificio = '10101-0'): Contrato
    {
        return Contrato::create([
            'empresa_id' => Empresa::create(['denominacion' => 'ACME', 'nit' => '1', 'tipo_empresa' => 'CLIENTE'])->id,
            'edificio_id' => Edificio::create(['codigo_edificio' => $codigoEdificio, 'denominacion' => 'Casa', 'tipo_edificacion' => 'Edificio'])->id,
            'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-12-31', 'monto_total' => 1000,
        ]);
    }

    public function test_cuotas_de_un_contrato_y_totales(): void
    {
        $this->ingresar();
        $contrato = $this->contrato();

        foreach ([1, 2] as $nro) {
            $this->postJson('/api/cuotas-contrato', ['contrato_id' => $contrato->id, 'nro_cuota' => $nro, 'fecha_cuota' => "2026-0{$nro}-15", 'monto_bs' => 500])
                ->assertCreated()->assertJsonPath('data.estado_cuota', 'PENDIENTE');
        }

        $this->postJson('/api/cuotas-contrato', ['contrato_id' => $contrato->id, 'nro_cuota' => 1, 'fecha_cuota' => '2026-03-15', 'monto_bs' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('nro_cuota');

        $this->getJson("/api/contratos/{$contrato->id}")->assertOk()->assertJsonPath('data.total_cuotas_bs', fn ($v) => (float) $v === 1000.0);
        $this->getJson('/api/contratos')->assertJsonPath('data.0.total_cuotas_bs', fn ($v) => (float) $v === 1000.0);
        $this->getJson("/api/cuotas-contrato?contrato_id={$contrato->id}")->assertJsonCount(2, 'data')->assertJsonPath('data.0.nro_cuota', 1);

        // No se elimina un contrato con cuotas.
        $this->deleteJson("/api/contratos/{$contrato->id}")->assertStatus(409);
    }

    public function test_cobranza_avanza_el_estado_de_su_cuota(): void
    {
        $this->ingresar();
        $contrato = $this->contrato();
        $otro = $this->contrato('20202-0');
        $cuota = CuotaContrato::create(['contrato_id' => $contrato->id, 'nro_cuota' => 1, 'fecha_cuota' => '2026-02-01', 'monto_bs' => 500]);
        $base = ['contrato_id' => $contrato->id, 'cuota_id' => $cuota->id, 'concepto' => '1RA CUOTA', 'monto_facturado' => 500, 'fecha_cobro' => '2026-02-10'];

        // Una cuota de otro contrato se rechaza.
        $ajena = CuotaContrato::create(['contrato_id' => $otro->id, 'nro_cuota' => 1, 'fecha_cuota' => '2026-02-01', 'monto_bs' => 5]);
        $this->postJson('/api/cobranzas', ['cuota_id' => $ajena->id] + $base)->assertUnprocessable()->assertJsonValidationErrors('cuota_id');

        $id = $this->postJson('/api/cobranzas', $base + ['nro_factura' => 'F-100', 'nit_facturado' => '123'])
            ->assertCreated()->assertJsonPath('data.estado', false)->assertJsonPath('data.cuota.nro_cuota', 1)->json('data.id');
        $this->assertSame('FACTURADA', $cuota->fresh()->estado_cuota);

        $this->putJson("/api/cobranzas/{$id}", $base + ['nro_factura' => 'F-100', 'cobrado_bs' => 500, 'comprobante_deposito' => 'D-1'])->assertOk();
        $this->assertSame('COBRADA', $cuota->fresh()->estado_cuota);

        $this->postJson("/api/cobranzas/{$id}/aprobar")->assertOk()->assertJsonPath('data.estado_registro', 'APR');
        $this->postJson("/api/cobranzas/{$id}/baja")->assertUnprocessable(); // cobranzas no usa alta/baja
        $this->assertNotNull(Cobranza::find($id));
    }

    public function test_los_modulos_sin_aprobacion_no_exponen_esas_rutas(): void
    {
        $this->ingresar();
        $pais = Pais::create(['nombre' => 'Bolivia']);

        $this->postJson("/api/paises/{$pais->id}/aprobar")->assertNotFound();
        $this->getJson('/api/paises?estado_registro=APR')->assertOk(); // el filtro se ignora donde no aplica
    }

    public function test_filtros_nuevos_de_los_modulos(): void
    {
        $this->ingresar();
        $g = $this->geografia();
        Persona::create($this->persona(['departamento_id' => $g['lp']->id]));
        Persona::create($this->persona(['numero_documento' => '2', 'tipo_persona' => 'COBRADOR', 'departamento_id' => $g['cbb']->id]));

        $this->getJson('/api/personas?tipo_persona=COBRADOR')->assertJsonCount(1, 'data');
        $this->getJson("/api/personas?departamento_id={$g['lp']->id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/personas?q=quispe')->assertJsonCount(2, 'data');
        $this->getJson('/api/personas?estado_registro=XXX')->assertUnprocessable();
    }
}
