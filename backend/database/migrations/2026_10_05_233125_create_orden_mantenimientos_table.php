<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orden_mantenimientos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_orden')->unique();
            $table->string('equipo_ascensor'); // El ascensor que van a revisar
            $table->date('fecha_programada');
            $table->enum('situacion', ['Pendiente', 'En Proceso', 'Finalizado'])->default('Pendiente');
            $table->text('descripcion_trabajo');

            // Relación con tu tabla de repuestos
            $table->foreignId('repuesto_id')->nullable()->constrained('repuestos')->nullOnDelete();
            $table->integer('cantidad_utilizada')->default(0);

            $table->boolean('estado')->default(true);
            $table->auditoria(); // <--- ¡Esta sola palabra pone todas las columnas!
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orden_mantenimientos');
    }
};
