<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clasificador jerárquico de bienes y equipos: grupo (1) > subgrupo 1 (2) > subgrupo 2 (3).
        Schema::create('grupos_bienes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique(); // Ej: 40000, 43000, 43340
            $table->string('nombre');
            $table->unsignedTinyInteger('nivel'); // 1, 2 o 3
            $table->foreignId('padre_id')->nullable()->constrained('grupos_bienes')->nullOnDelete();
            $table->boolean('estado')->default(true);
            $table->auditoria();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupos_bienes');
    }
};
