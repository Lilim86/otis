<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agenda del gerente general: citas, consultas, reuniones y visitas que registra Secretaría.
        Schema::create('citas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('tipo', 12)->default('CITA')->index(); // CITA, CONSULTA, REUNION, VISITA
            $table->string('responsable')->index(); // de quién es la agenda (gerente general)
            $table->dateTime('fecha_inicio')->index();
            $table->dateTime('fecha_fin');
            $table->string('lugar')->nullable();
            $table->string('con_quien')->nullable(); // visitante o contacto
            $table->string('telefono', 30)->nullable();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('edificio_id')->nullable()->constrained('edificios')->nullOnDelete();
            $table->text('motivo')->nullable();
            $table->string('situacion', 12)->default('PROGRAMADA')->index(); // PROGRAMADA, CONFIRMADA, ATENDIDA, CANCELADA
            $table->boolean('estado')->default(true);
            $table->auditoria();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citas');
    }
};
