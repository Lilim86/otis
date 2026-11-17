<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos_catalogo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('categoria', 60)->index();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->json('especificaciones')->nullable(); // lista de { etiqueta, valor }
            $table->string('imagen_url', 500)->nullable();
            $table->string('catalogo_url', 500)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('estado')->default(true);
            $table->auditoria();
        });

        Schema::create('pedidos_catalogo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos_catalogo');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('edificio_id')->nullable()->constrained('edificios')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cita_id')->nullable()->constrained('citas')->nullOnDelete();
            $table->unsignedSmallInteger('cantidad')->default(1);
            $table->text('observaciones')->nullable();
            $table->date('fecha_compromiso')->index();
            $table->string('situacion', 12)->default('PENDIENTE')->index(); // PENDIENTE, ATENDIDO, CANCELADO
            $table->boolean('notificado_correo')->default(false);
            $table->boolean('estado')->default(true);
            $table->auditoria();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_catalogo');
        Schema::dropIfExists('productos_catalogo');
    }
};
