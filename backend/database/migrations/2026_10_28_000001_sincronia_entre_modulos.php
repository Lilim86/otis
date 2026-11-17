<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quién retira del almacén, para qué visita y con qué motivo (consumo, préstamo de herramienta, devolución...).
        Schema::table('movimientos_almacen', function (Blueprint $table) {
            $table->foreignId('personal_id')->nullable()->after('compra_id')->constrained('personal')->nullOnDelete();
            $table->foreignId('visita_id')->nullable()->after('personal_id')->constrained('visitas_mantenimiento')->nullOnDelete();
            $table->string('motivo', 12)->nullable()->after('visita_id')->index();
        });

        // Cuándo el técnico empezó la visita (situación EN_CURSO): permite ver quién está en campo.
        Schema::table('visitas_mantenimiento', function (Blueprint $table) {
            $table->timestamp('iniciada_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('visitas_mantenimiento', fn (Blueprint $t) => $t->dropColumn('iniciada_en'));
        Schema::table('movimientos_almacen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visita_id');
            $table->dropConstrainedForeignId('personal_id');
            $table->dropColumn('motivo');
        });
    }
};
