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
        Schema::create('repuestos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_parte')->unique(); // Ej: REP-OTIS-001
            $table->string('nombre'); // Ej: Cable de tracción, Tarjeta controladora Gen2
            $table->integer('stock')->default(0);
            $table->decimal('precio', 10, 2); // Precio unitario en Bolivianos (BOB)
            $table->text('ubicacion_almacen'); // Ej: Almacén Central La Paz - Estante 3
            $table->boolean('estado')->default(true);
            $table->auditoria(); // <--- ¡Esta sola palabra pone todas las columnas!
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('repuestos');
    }
};
