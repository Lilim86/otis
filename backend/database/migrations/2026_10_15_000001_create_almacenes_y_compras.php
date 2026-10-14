<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('almacenes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre'); // Ej: ALMACEN DE HERRAMIENTAS LA PAZ CGI
            $table->string('tipo_almacen', 1); // A, H, R, M
            $table->foreignId('regional_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->boolean('estado')->default(true);
            $table->auditoria();
        });

        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_compra', 30)->unique(); // Cite del contrato de compra, ej. ALMHB-000006
            $table->foreignId('proveedor_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->foreignId('proveedor_empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('regional_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->string('unidad_oficina', 20)->nullable();
            $table->foreignId('almacen_id')->nullable()->constrained('almacenes')->nullOnDelete();
            $table->date('fecha_compra');
            $table->decimal('total_bs', 14, 2)->default(0);
            $table->decimal('total_usd', 14, 2)->default(0);
            $table->text('observacion')->nullable();
            $table->aprobacion();
            $table->boolean('estado')->default(true);
            $table->auditoria();
        });

        Schema::create('compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario_bs', 12, 2);
            $table->timestamps();
        });

        Schema::create('movimientos_almacen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('almacen_id')->constrained('almacenes')->restrictOnDelete();
            $table->foreignId('insumo_id')->constrained('insumos')->restrictOnDelete();
            $table->string('tipo', 10); // INGRESO o SALIDA
            $table->decimal('cantidad', 12, 2);
            $table->decimal('costo_unitario_bs', 12, 2)->default(0);
            $table->date('fecha');
            $table->string('referencia')->nullable(); // Ej: nro de orden de salida
            $table->foreignId('compra_id')->nullable()->constrained('compras')->nullOnDelete();
            $table->text('observacion')->nullable();
            // Un movimiento afecta el stock al registrarse (APR); anularlo (ANL) lo revierte.
            $table->string('estado_registro', 3)->default('APR')->index();
            $table->boolean('estado')->default(true);
            $table->auditoria();
            $table->index(['almacen_id', 'insumo_id']);
        });

        Schema::create('facturas_proveedor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->date('fecha_factura');
            $table->string('nro_factura', 40);
            $table->decimal('importe_bs', 14, 2);
            $table->string('concepto')->nullable();
            $table->aprobacion();
            $table->boolean('estado')->default(true);
            $table->auditoria();
        });

        // Orden de cancelación (egresos): pago a proveedores.
        Schema::create('ordenes_cancelacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->nullable()->constrained('compras')->nullOnDelete();
            $table->foreignId('factura_proveedor_id')->nullable()->constrained('facturas_proveedor')->nullOnDelete();
            $table->string('nro_oc', 40)->unique();
            $table->date('fecha_pago');
            $table->foreignId('cuenta_origen_id')->nullable()->constrained('cuentas_bancarias')->nullOnDelete();
            $table->string('comprobante_deposito', 40)->nullable();
            $table->decimal('pagado_bs', 14, 2)->default(0);
            $table->decimal('pagado_usd', 14, 2)->default(0);
            $table->string('concepto')->nullable();
            $table->aprobacion();
            $table->boolean('estado')->default(true);
            $table->auditoria();
        });
    }

    public function down(): void
    {
        foreach (['ordenes_cancelacion', 'facturas_proveedor', 'movimientos_almacen', 'compra_detalles', 'compras', 'almacenes'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
