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
        Schema::create('ascensores', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // Ej: ASC-LPZ-001 (Único y oficial)
            $table->string('cliente_edificio'); // Nombre del edificio
            $table->foreignId('zona_id')->constrained('zonas')->onDelete('cascade');
            $table->string('modelo')->nullable(); // Ej: Gen2, Elevonic
            $table->boolean('estado')->default(true);
            $table->auditoria(); // <--- ¡Esta sola palabra pone todas las columnas!
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ascensores');
    }
};
