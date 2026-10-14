<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('denominacion'); // Ej: ASOCIACION DE COPROPIETARIOS
            $table->string('nit');
            $table->string('tipo_empresa'); // Ej: EMPRESA - CLIENTE
            $table->string('sigla')->nullable();
            $table->boolean('estado')->default(true);
            $table->auditoria(); // <--- ¡Esta sola palabra pone todas las columnas!
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
