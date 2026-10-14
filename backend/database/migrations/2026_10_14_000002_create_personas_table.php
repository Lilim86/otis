<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Registro general de personas (clientes, proveedores, cobradores, técnicos...), como en SOFIA.
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_documento', 10)->default('CI');
            $table->string('numero_documento', 30)->unique();
            $table->string('expedido_en', 3)->nullable();
            $table->string('primer_apellido');
            $table->string('segundo_apellido')->nullable();
            $table->string('nombres');
            $table->string('iniciales', 10)->nullable();
            $table->string('tipo_persona', 30);
            $table->string('nit', 30)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('cargo_puesto')->nullable(); // Cargo o puesto del beneficiario
            $table->string('telefono_fijo', 30)->nullable();
            $table->string('telefono_oficina', 30)->nullable();
            $table->string('telefono_celular', 30);
            $table->string('correo_personal');
            $table->string('correo_institucional')->nullable();
            $table->foreignId('nacionalidad_id')->nullable()->constrained('paises')->nullOnDelete();
            $table->ubicacion();
            $table->foreignId('edificio_id')->nullable()->constrained('edificios')->nullOnDelete();
            $table->string('nro_vivienda', 20)->nullable();
            $table->string('nro_piso', 20)->nullable();
            $table->string('nro_depto', 20)->nullable();
            $table->aprobacion();
            $table->boolean('estado')->default(true);
            $table->auditoria();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
