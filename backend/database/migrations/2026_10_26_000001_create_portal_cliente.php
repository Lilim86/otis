<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un usuario con rol Cliente pertenece a una empresa y solo ve lo de ella.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
        });

        // Cronograma e historial de mantenimiento por contrato (preventivos programados y correctivos solicitados).
        Schema::create('visitas_mantenimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->unsignedSmallInteger('nro_visita')->nullable();
            $table->string('tipo', 12)->default('PREVENTIVO')->index(); // PREVENTIVO, CORRECTIVO
            $table->date('fecha_programada')->index();
            $table->date('fecha_realizada')->nullable();
            $table->string('situacion', 12)->default('PROGRAMADA')->index(); // PROGRAMADA, SOLICITADA, REALIZADA, REPROGRAMADA, CANCELADA
            $table->foreignId('tecnico_id')->nullable()->constrained('personal')->nullOnDelete();
            $table->text('descripcion')->nullable();
            $table->text('informe')->nullable();
            $table->unsignedTinyInteger('calificacion')->nullable(); // 1 a 5, la pone el cliente
            $table->text('comentario_cliente')->nullable();
            $table->foreignId('solicitada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('estado')->default(true);
            $table->auditoria();
        });

        // Pagos por transferencia que declara el cliente; Financiero los verifica contra el extracto.
        Schema::create('pagos_clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->foreignId('cuota_id')->nullable()->constrained('cuotas_contrato')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('monto_bs', 12, 2);
            $table->date('fecha_pago');
            $table->string('nro_transaccion', 60);
            $table->string('banco_origen', 100)->nullable();
            $table->string('cuenta_destino', 40);
            $table->text('observacion')->nullable();
            $table->text('motivo_rechazo')->nullable();
            $table->foreignId('cobranza_id')->nullable()->constrained('cobranzas')->nullOnDelete();
            $table->boolean('estado')->default(true);
            $table->aprobacion(); // REG = en verificación, APR = confirmado, ANL = rechazado
            $table->auditoria();
            $table->unique(['cuenta_destino', 'nro_transaccion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_clientes');
        Schema::dropIfExists('visitas_mantenimiento');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_id');
        });
    }
};
