<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitas_mantenimiento', function (Blueprint $table) {
            // Prioridad de una falla reportada (P1 crítica ... P4 baja), el motivo y hasta cuándo hay que atenderla.
            $table->string('prioridad', 2)->nullable()->index();
            $table->string('motivo_prioridad', 255)->nullable();
            $table->timestamp('objetivo_respuesta_en')->nullable();
            $table->boolean('asignada_automaticamente')->default(false);
            // Orden de la visita dentro de la ruta del día del técnico (lo calcula el optimizador).
            $table->unsignedSmallInteger('orden_ruta')->nullable();
        });

        // Cada vez que se aplica una ruta óptima queda el antes y el después: así se mide el ahorro acumulado.
        Schema::create('optimizaciones_ruta', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_ruta')->index();
            $table->foreignId('tecnico_id')->constrained('personal')->cascadeOnDelete();
            $table->unsignedSmallInteger('visitas');
            $table->decimal('km_antes', 10, 2);
            $table->decimal('km_despues', 10, 2);
            $table->decimal('minutos_antes', 10, 1);
            $table->decimal('minutos_despues', 10, 1);
            $table->string('metodo', 60);
            $table->foreignId('aplicado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('optimizaciones_ruta');
        Schema::table('visitas_mantenimiento', fn (Blueprint $t) => $t->dropColumn(['prioridad', 'motivo_prioridad', 'objetivo_respuesta_en', 'asignada_automaticamente', 'orden_ruta']));
    }
};
