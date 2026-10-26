<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un técnico es un usuario vinculado a una persona del personal: ve solo sus visitas.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('personal_id')->nullable()->constrained('personal')->nullOnDelete();
        });

        // Foto o PDF de la transferencia que sube el cliente.
        Schema::table('pagos_clientes', function (Blueprint $table) {
            $table->string('comprobante_path')->nullable();
            $table->string('comprobante_nombre')->nullable();
        });

        // Conformidad firmada por el cliente al cerrar una visita.
        Schema::table('visitas_mantenimiento', function (Blueprint $table) {
            $table->longText('firma_cliente')->nullable(); // imagen PNG en base64
            $table->string('firmado_por')->nullable();
            $table->timestamp('firmado_en')->nullable();
        });

        // Avisos automáticos ya enviados, para no repetirlos.
        Schema::create('avisos_enviados', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30);
            $table->unsignedBigInteger('referencia_id');
            $table->string('destinatario');
            $table->timestamp('enviado_en')->useCurrent();
            $table->unique(['tipo', 'referencia_id', 'destinatario']);
        });

        // Bitácora de facturas y recibos enviados por correo.
        Schema::create('envios_comprobante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cobranza_id')->constrained('cobranzas')->cascadeOnDelete();
            $table->string('tipo', 10); // RECIBO, FACTURA
            $table->string('destinatario');
            $table->foreignId('enviado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('resultado', 10); // ENVIADO, FALLIDO
            $table->text('error')->nullable();
            $table->timestamp('enviado_en')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_comprobante');
        Schema::dropIfExists('avisos_enviados');
        Schema::table('visitas_mantenimiento', fn (Blueprint $t) => $t->dropColumn(['firma_cliente', 'firmado_por', 'firmado_en']));
        Schema::table('pagos_clientes', fn (Blueprint $t) => $t->dropColumn(['comprobante_path', 'comprobante_nombre']));
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('personal_id'));
    }
};
