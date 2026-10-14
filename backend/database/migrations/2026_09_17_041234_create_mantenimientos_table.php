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
        Schema::create('mantenimientos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_ascensor'); // Identificador del ascensor
            $table->foreignId('zona_id')->constrained('zonas')->onDelete('cascade'); // Dónde está ubicado geográficamente
            $table->string('tipo'); // Preventivo o Correctivo
            $table->text('detalles');
            $table->date('fecha_programada');
            $table->boolean('estado')->default(true);
            $table->auditoria(); // <--- ¡Esta sola palabra pone todas las columnas!
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mantenimientos');
    }
};
