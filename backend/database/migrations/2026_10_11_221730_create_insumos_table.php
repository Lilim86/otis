<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insumos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique(); // Ej: HERR-0493
            $table->string('descripcion'); // Ej: ACEITERA DE 500 ML
            $table->string('tipo'); // HERRAMIENTA, REPUESTO, MATERIAL
            $table->decimal('stock_actual', 10, 2)->default(0); // El Saldo Físico
            $table->boolean('estado')->default(true);
            $table->auditoria(); // <--- ¡Esta sola palabra pone todas las columnas!
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insumos');
    }
};
