<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobranzas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->onDelete('cascade');
            $table->string('concepto'); // Ej: 1RA CUOTA, PAGO PROVISIONAL
            $table->decimal('monto_facturado', 10, 2);
            $table->date('fecha_cobro');
            $table->string('nro_factura')->nullable();
            $table->boolean('estado')->default(true);
            $table->auditoria(); // <--- ¡Esta sola palabra pone todas las columnas!
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranzas');
    }
};
