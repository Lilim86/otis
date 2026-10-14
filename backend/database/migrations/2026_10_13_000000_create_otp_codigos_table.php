<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Códigos de un solo uso enviados por correo. Son temporales y de seguridad,
        // por eso no llevan las columnas de auditoría de las tablas de negocio.
        Schema::create('otp_codigos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('codigo_hash'); // nunca se guarda el código en claro
            $table->timestamp('expira_en');
            $table->unsignedTinyInteger('intentos')->default(0);
            $table->timestamp('usado_en')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'usado_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codigos');
    }
};
