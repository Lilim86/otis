<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // Ej: 36E0074
            $table->string('descripcion'); // Ej: ASCENSOR SOCIAL PANORAMICO
            $table->foreignId('edificio_id')->nullable()->constrained('edificios')->nullOnDelete(); // ¿En qué edificio está?
            $table->string('industria')->nullable(); // Ej: BRASIL
            $table->string('marca')->nullable(); // Ej: OTIS, WECO
            $table->string('modelo')->nullable();
            $table->integer('paradas')->nullable(); // Número de pisos/paradas
            $table->boolean('estado')->default(true);
            $table->auditoria(); // <--- ¡Esta sola palabra pone todas las columnas!
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipos');
    }
};
