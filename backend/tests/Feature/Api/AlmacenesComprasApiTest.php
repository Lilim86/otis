<?php

namespace Tests\Feature\Api;

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\Insumo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AlmacenesComprasApiTest extends TestCase
{
    use RefreshDatabase;

    private function ingresar(): void
    {
        Sanctum::actingAs(User::create(['name' => 'Admin', 'username' => 'admin', 'email' => 'admin@otis.com', 'password' => 'secret123']));
    }

    private function almacen(): Almacen
    {
        return Almacen::create(['codigo' => '4', 'nombre' => 'ALMACEN DE HERRAMIENTAS LA PAZ CGI', 'tipo_almacen' => 'H']);
    }

    private function insumo(string $codigo = 'HERR-0493', float $stock = 0): Insumo
    {
        return Insumo::create(['codigo' => $codigo, 'descripcion' => 'ACEITERA', 'tipo' => 'HERRAMIENTA', 'stock_actual' => $stock]);
    }

    private function movimiento(Almacen $a, Insumo $i, string $tipo, float $cantidad, array $extra = [])
    {
        return $this->postJson('/api/movimientos-almacen', $extra + [
            'almacen_id' => $a->id, 'insumo_id' => $i->id, 'tipo' => $tipo, 'cantidad' => $cantidad, 'costo_unitario_bs' => 10, 'fecha' => now()->toDateString(),
        ]);
    }

    private function stock(Insumo $i): float
    {
        return (float) $i->fresh()->stock_actual;
    }

    public function test_ingresos_y_salidas_actualizan_el_stock_y_el_saldo(): void
    {
        $this->ingresar();
        $almacen = $this->almacen();
        $insumo = $this->insumo();

        $this->movimiento($almacen, $insumo, 'INGRESO', 24)->assertCreated()->assertJsonPath('data.estado_registro', 'APR');
        $this->movimiento($almacen, $insumo, 'SALIDA', 4)->assertCreated();
        $this->assertSame(20.0, $this->stock($insumo));

        $this->getJson("/api/almacenes/{$almacen->id}/inventario")->assertOk()
            ->assertJsonPath('data.bienes.0.codigo', 'HERR-0493')
            ->assertJsonPath('data.bienes.0.cantidad_ingresada', 24)
            ->assertJsonPath('data.bienes.0.cantidad_salida', 4)
            ->assertJsonPath('data.bienes.0.saldo_fisico', 20)
            ->assertJsonPath('data.bienes.0.valor_bs', 200);
    }

    public function test_no_se_puede_sacar_mas_de_lo_que_hay(): void
    {
        $this->ingresar();
        $almacen = $this->almacen();
        $otro = Almacen::create(['codigo' => '5', 'nombre' => 'OTRO', 'tipo_almacen' => 'A']);
        $insumo = $this->insumo();

        $this->movimiento($almacen, $insumo, 'INGRESO', 5);
        $this->movimiento($almacen, $insumo, 'SALIDA', 6)->assertUnprocessable()->assertJsonValidationErrors('cantidad');
        // El saldo es por almacén: lo que hay en uno no sirve en otro.
        $this->movimiento($otro, $insumo, 'SALIDA', 1)->assertUnprocessable();
        $this->assertSame(5.0, $this->stock($insumo));
    }

    public function test_validaciones_del_movimiento(): void
    {
        $this->ingresar();
        $almacen = $this->almacen();
        $insumo = $this->insumo();

        $this->movimiento($almacen, $insumo, 'INGRESO', 0)->assertUnprocessable()->assertJsonValidationErrors('cantidad');
        $this->movimiento($almacen, $insumo, 'TRASPASO', 1)->assertUnprocessable()->assertJsonValidationErrors('tipo');
        $this->movimiento($almacen, $insumo, 'INGRESO', 1, ['fecha' => '2999-01-01'])->assertUnprocessable()->assertJsonValidationErrors('fecha');
    }

    public function test_un_movimiento_no_se_edita_ni_se_elimina_pero_se_anula(): void
    {
        $this->ingresar();
        $almacen = $this->almacen();
        $insumo = $this->insumo();
        $ingreso = $this->movimiento($almacen, $insumo, 'INGRESO', 10)->json('data.id');

        $this->putJson("/api/movimientos-almacen/{$ingreso}", [])->assertStatus(405);
        $this->deleteJson("/api/movimientos-almacen/{$ingreso}")->assertStatus(409);

        $salida = $this->movimiento($almacen, $insumo, 'SALIDA', 4)->json('data.id');
        // Parte del ingreso ya salió: no se puede anular.
        $this->postJson("/api/movimientos-almacen/{$ingreso}/anular")->assertUnprocessable();

        $this->postJson("/api/movimientos-almacen/{$salida}/anular")->assertOk()->assertJsonPath('data.estado_registro', 'ANL');
        $this->assertSame(10.0, $this->stock($insumo));
        $this->postJson("/api/movimientos-almacen/{$ingreso}/anular")->assertOk();
        $this->assertSame(0.0, $this->stock($insumo));
        $this->postJson("/api/movimientos-almacen/{$ingreso}/anular")->assertUnprocessable();
    }

    public function test_un_almacen_con_movimientos_no_se_elimina(): void
    {
        $this->ingresar();
        $almacen = $this->almacen();
        $this->movimiento($almacen, $this->insumo(), 'INGRESO', 1);

        $this->deleteJson("/api/almacenes/{$almacen->id}")->assertStatus(409);
    }

    // ------------------------------------------------------------------- compras

    private function compra(Almacen $almacen, Insumo $insumo, array $extra = []): array
    {
        $proveedor = Empresa::create(['denominacion' => 'PROVEEDOR SRL', 'nit' => '99', 'tipo_empresa' => 'EMPRESA - PROVEEDOR']);

        return $extra + [
            'codigo_compra' => 'ALMHB-000006', 'proveedor_empresa_id' => $proveedor->id, 'almacen_id' => $almacen->id, 'fecha_compra' => '2026-03-16',
            'detalles' => [['insumo_id' => $insumo->id, 'cantidad' => 10, 'precio_unitario_bs' => 25.5]],
        ];
    }

    public function test_la_compra_calcula_sus_totales_en_el_servidor(): void
    {
        $this->ingresar();
        $datos = $this->compra($this->almacen(), $this->insumo());

        $this->postJson('/api/compras', $datos + ['total_bs' => 1])->assertCreated()
            ->assertJsonPath('data.estado_registro', 'REG')
            ->assertJsonPath('data.total_bs', fn ($v) => (float) $v === 255.0)
            ->assertJsonPath('data.total_usd', fn ($v) => (float) $v === 36.64)
            ->assertJsonCount(1, 'data.detalles');
    }

    public function test_aprobar_la_compra_ingresa_al_almacen_y_anularla_lo_revierte(): void
    {
        $this->ingresar();
        $almacen = $this->almacen();
        $insumo = $this->insumo();
        $id = $this->postJson('/api/compras', $this->compra($almacen, $insumo))->json('data.id');

        $this->assertSame(0.0, $this->stock($insumo));
        $this->postJson("/api/compras/{$id}/aprobar")->assertOk()->assertJsonPath('data.estado_registro', 'APR');
        $this->assertSame(10.0, $this->stock($insumo));
        $this->getJson("/api/movimientos-almacen?compra_id={$id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.referencia', 'ALMHB-000006');

        // Una compra aprobada no se edita.
        $this->putJson("/api/compras/{$id}", $this->compra($almacen, $insumo))->assertStatus(409);

        $this->postJson("/api/compras/{$id}/anular")->assertOk()->assertJsonPath('data.estado_registro', 'ANL');
        $this->assertSame(0.0, $this->stock($insumo));
    }

    public function test_no_se_anula_una_compra_si_lo_comprado_ya_salio(): void
    {
        $this->ingresar();
        $almacen = $this->almacen();
        $insumo = $this->insumo();
        $id = $this->postJson('/api/compras', $this->compra($almacen, $insumo))->json('data.id');
        $this->postJson("/api/compras/{$id}/aprobar")->assertOk();
        $this->movimiento($almacen, $insumo, 'SALIDA', 8)->assertCreated();

        $this->postJson("/api/compras/{$id}/anular")->assertUnprocessable();
        $this->assertSame(2.0, $this->stock($insumo));
        $this->assertDatabaseHas('compras', ['id' => $id, 'estado_registro' => 'APR']);
    }

    public function test_aprobar_exige_almacen_y_detalle(): void
    {
        $this->ingresar();
        $almacen = $this->almacen();
        $insumo = $this->insumo();

        $sinAlmacen = $this->postJson('/api/compras', $this->compra($almacen, $insumo, ['almacen_id' => null, 'codigo_compra' => 'C1']))->json('data.id');
        $this->postJson("/api/compras/{$sinAlmacen}/aprobar")->assertUnprocessable()->assertJsonValidationErrors('almacen_id');

        $sinDetalle = $this->postJson('/api/compras', $this->compra($almacen, $insumo, ['detalles' => [], 'codigo_compra' => 'C2']))->json('data.id');
        $this->postJson("/api/compras/{$sinDetalle}/aprobar")->assertUnprocessable()->assertJsonValidationErrors('detalles');
    }

    public function test_validaciones_de_la_compra(): void
    {
        $this->ingresar();
        $almacen = $this->almacen();
        $insumo = $this->insumo();

        $this->postJson('/api/compras', ['codigo_compra' => 'X', 'fecha_compra' => '2026-01-01'])->assertUnprocessable()
            ->assertJsonValidationErrors(['proveedor_id', 'proveedor_empresa_id']);
        $this->postJson('/api/compras', $this->compra($almacen, $insumo, ['detalles' => [['insumo_id' => $insumo->id, 'cantidad' => 0, 'precio_unitario_bs' => 1]]]))
            ->assertUnprocessable();
        $this->postJson('/api/compras', $this->compra($almacen, $insumo, ['detalles' => [
            ['insumo_id' => $insumo->id, 'cantidad' => 1, 'precio_unitario_bs' => 1], ['insumo_id' => $insumo->id, 'cantidad' => 1, 'precio_unitario_bs' => 1],
        ]]))->assertUnprocessable();
    }

    public function test_facturas_y_pagos_de_una_compra(): void
    {
        $this->ingresar();
        $id = $this->postJson('/api/compras', $this->compra($this->almacen(), $this->insumo()))->json('data.id');

        $factura = $this->postJson('/api/facturas-proveedor', ['compra_id' => $id, 'fecha_factura' => '2026-03-20', 'nro_factura' => 'F-77', 'importe_bs' => 255])
            ->assertCreated()->json('data.id');
        $this->postJson('/api/facturas-proveedor', ['compra_id' => $id, 'fecha_factura' => '2026-03-20', 'nro_factura' => 'F-77', 'importe_bs' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('nro_factura');

        $pago = ['compra_id' => $id, 'factura_proveedor_id' => $factura, 'nro_oc' => 'OC-1', 'fecha_pago' => '2026-03-25', 'pagado_bs' => 200];
        $oc = $this->postJson('/api/ordenes-cancelacion', $pago)->assertCreated()->json('data.id');
        // Con 200 ya pagados, otros 100 superan el total de 255.
        $this->postJson('/api/ordenes-cancelacion', ['nro_oc' => 'OC-2', 'pagado_bs' => 100] + $pago)->assertUnprocessable()->assertJsonValidationErrors('pagado_bs');
        $this->postJson('/api/ordenes-cancelacion', ['nro_oc' => 'OC-3', 'pagado_bs' => 55] + $pago)->assertCreated();

        // No se elimina una compra con facturas o pagos.
        $this->deleteJson("/api/compras/{$id}")->assertStatus(409);

        // Con un pago aprobado, la compra no se anula.
        $this->postJson("/api/ordenes-cancelacion/{$oc}/aprobar")->assertOk();
        $this->postJson("/api/compras/{$id}/anular")->assertUnprocessable();
    }
}
