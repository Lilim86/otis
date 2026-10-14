<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cargo_id')->constrained('cargos')->onDelete('cascade');
            $table->foreignId('profesion_id')->constrained('profesiones')->onDelete('cascade');
            $table->string('ci')->unique();
            $table->string('nombres');
            $table->string('apellidos');
            $table->boolean('estado')->default(true);
            $table->auditoria(); // <--- ¡Esta sola palabra pone todas las columnas!
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal');
    }
};
