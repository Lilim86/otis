<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edificios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_edificio')->unique(); // Ej: 10101-0
            $table->string('denominacion'); // Ej: CASA PARTICULAR - SUCRE
            $table->string('tipo_edificacion'); // Ej: EDIFICIO DE DEPARTAMENTOS
            $table->boolean('estado')->default(true);
            $table->auditoria(); // <--- ¡Esta sola palabra pone todas las columnas!
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edificios');
    }
};
