<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal del cobrador: cada edificio tiene su cobrador (una persona de tipo COBRADOR, enlazada a su usuario),
 * cada cobranza dice cómo se pagó y Tesorería aprueba u observa lo que el cobrador le envía.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('persona_id')->nullable()->constrained('personas')->nullOnDelete();
        });

        Schema::table('edificios', function (Blueprint $table) {
            $table->foreignId('cobrador_id')->nullable()->constrained('personas')->nullOnDelete();
        });

        Schema::table('cobranzas', function (Blueprint $table) {
            $table->string('tipo_cobro', 15)->nullable();          // MANTENIMIENTO o CONTRATO (contrato y renovación)
            $table->string('metodo_pago', 15)->nullable();         // EFECTIVO, CHEQUE o TRANSFERENCIA
            $table->string('nro_cheque', 30)->nullable();
            $table->string('banco_cheque', 80)->nullable();
            // null = cobranza cargada en la oficina; si la hizo un cobrador: PENDIENTE -> ENVIADO -> APROBADO u OBSERVADO.
            $table->string('situacion_tesoreria', 10)->nullable()->index();
            $table->timestamp('enviado_en')->nullable();
            $table->timestamp('revisado_en')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('observacion_tesoreria', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cobranzas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revisado_por');
            $table->dropColumn(['tipo_cobro', 'metodo_pago', 'nro_cheque', 'banco_cheque', 'situacion_tesoreria', 'enviado_en', 'revisado_en', 'observacion_tesoreria']);
        });

        Schema::table('edificios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cobrador_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('persona_id');
        });
    }
};
