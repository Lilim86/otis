<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->string('codigo_contrato', 30)->nullable()->unique();
            $table->foreignId('regional_id')->nullable()->constrained('departamentos')->nullOnDelete(); // regional = departamento
            $table->string('unidad_oficina', 20)->nullable(); // Ej: DCOMC, DNINS
            $table->aprobacion();
        });

        // Cuotas de cada contrato: base de los reportes de seguimiento y cobranzas.
        Schema::create('cuotas_contrato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->unsignedSmallInteger('nro_cuota');
            $table->date('fecha_cuota');
            $table->string('estado_cuota', 12)->default('PENDIENTE');
            $table->decimal('monto_bs', 12, 2);
            $table->string('concepto')->nullable();
            $table->string('certificado')->nullable();
            $table->date('fecha_conformidad')->nullable();
            $table->boolean('estado')->default(true);
            $table->auditoria();
            $table->unique(['contrato_id', 'nro_cuota']);
        });

        Schema::table('cobranzas', function (Blueprint $table) {
            $table->foreignId('cuota_id')->nullable()->constrained('cuotas_contrato')->nullOnDelete();
            $table->foreignId('cuenta_bancaria_id')->nullable()->constrained('cuentas_bancarias')->nullOnDelete();
            $table->foreignId('cobrador_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->string('comprobante_deposito', 40)->nullable();
            $table->string('nro_recibo', 40)->nullable();
            $table->decimal('cobrado_bs', 12, 2)->default(0);
            $table->decimal('cobrado_usd', 12, 2)->default(0);
            $table->date('fecha_facturacion')->nullable();
            $table->string('nit_facturado', 30)->nullable();
            $table->string('factura_a_nombre')->nullable();
            $table->aprobacion();
        });
    }

    public function down(): void
    {
        Schema::table('cobranzas', function (Blueprint $table) {
            foreach (['cuota_id', 'cuenta_bancaria_id', 'cobrador_id'] as $c) {
                $table->dropConstrainedForeignId($c);
            }
            $table->dropColumn(['comprobante_deposito', 'nro_recibo', 'cobrado_bs', 'cobrado_usd', 'fecha_facturacion', 'nit_facturado', 'factura_a_nombre', 'estado_registro']);
        });

        Schema::dropIfExists('cuotas_contrato');

        Schema::table('contratos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('regional_id');
            $table->dropColumn(['codigo_contrato', 'unidad_oficina', 'estado_registro']);
        });
    }
};
