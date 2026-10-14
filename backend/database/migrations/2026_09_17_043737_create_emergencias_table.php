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
        Schema::create('emergencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ascensor_id')->constrained('ascensores')->onDelete('cascade'); // Ascensor afectado
            $table->string('gravedad'); // Leve, Moderada, Crítica
            $table->text('descripcion_fallo');
            $table->string('situacion')->default('Reportado'); // Reportado, En Proceso, Solucionado
            $table->boolean('estado')->default(true);
            $table->auditoria(); // <--- ¡Esta sola palabra pone todas las columnas!
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergencias');
    }
};
