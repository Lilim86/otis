<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tesorería: recibos oficiales de ingresos y traspasos de ingresos (cobros por extractos).
        Schema::create('ingresos_tesoreria', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20); // RECIBO_OFICIAL o TRASPASO_EXTRACTO
            $table->string('nro_recibo', 40)->unique();
            $table->date('fecha');
            $table->foreignId('cuenta_bancaria_id')->constrained('cuentas_bancarias')->restrictOnDelete();
            $table->foreignId('cobranza_id')->nullable()->constrained('cobranzas')->nullOnDelete();
            $table->decimal('monto_bs', 14, 2)->default(0);
            $table->decimal('monto_usd', 14, 2)->default(0);
            $table->string('concepto')->nullable();
            $table->aprobacion();
            $table->boolean('estado')->default(true);
            $table->auditoria();
        });

        Schema::create('traspasos_cuentas', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->foreignId('cuenta_origen_id')->constrained('cuentas_bancarias')->restrictOnDelete();
            $table->foreignId('cuenta_destino_id')->constrained('cuentas_bancarias')->restrictOnDelete();
            $table->decimal('monto', 14, 2);
            $table->string('referencia', 60)->nullable();
            $table->string('concepto')->nullable();
            $table->aprobacion();
            $table->boolean('estado')->default(true);
            $table->auditoria();
        });

        // Fondos a rendir: cargos de cuenta, fondos para viajes y caja chica.
        Schema::create('fondos_rendir', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20); // CARGO_CUENTA, FONDO_VIAJE, CAJA_CHICA
            $table->string('codigo', 30)->unique();
            $table->foreignId('responsable_id')->constrained('personas')->restrictOnDelete();
            $table->foreignId('regional_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('monto_asignado_bs', 14, 2);
            $table->string('concepto')->nullable();
            $table->aprobacion();
            $table->boolean('estado')->default(true);
            $table->auditoria();
        });

        Schema::create('rendiciones_fondo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fondo_id')->constrained('fondos_rendir')->restrictOnDelete();
            $table->date('fecha');
            $table->string('nro_comprobante', 40)->nullable();
            $table->string('concepto');
            $table->decimal('monto_bs', 14, 2);
            $table->aprobacion();
            $table->boolean('estado')->default(true);
            $table->auditoria();
        });

        // RRHH: planillas de sueldos y contratos de técnicos.
        Schema::create('planillas', function (Blueprint $table) {
            $table->id();
            $table->string('periodo', 7); // 2026-10
            $table->string('tipo', 20)->default('SUELDOS');
            $table->foreignId('regional_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $table->decimal('total_haberes_bs', 14, 2)->default(0);
            $table->decimal('total_descuentos_bs', 14, 2)->default(0);
            $table->decimal('total_liquido_bs', 14, 2)->default(0);
            $table->text('observacion')->nullable();
            $table->aprobacion();
            $table->boolean('estado')->default(true);
            $table->auditoria();
        });

        Schema::create('planilla_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planilla_id')->constrained('planillas')->cascadeOnDelete();
            $table->foreignId('personal_id')->constrained('personal')->restrictOnDelete();
            $table->decimal('haber_basico_bs', 12, 2);
            $table->decimal('descuentos_bs', 12, 2)->default(0);
            $table->decimal('liquido_bs', 12, 2);
            $table->timestamps();
            $table->unique(['planilla_id', 'personal_id']);
        });

        Schema::create('contratos_tecnicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personal_id')->constrained('personal')->restrictOnDelete();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->decimal('monto_mensual_bs', 12, 2);
            $table->string('objeto');
            $table->aprobacion();
            $table->boolean('estado')->default(true);
            $table->auditoria();
        });
    }

    public function down(): void
    {
        foreach (['contratos_tecnicos', 'planilla_detalles', 'planillas', 'rendiciones_fondo', 'fondos_rendir', 'traspasos_cuentas', 'ingresos_tesoreria'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
