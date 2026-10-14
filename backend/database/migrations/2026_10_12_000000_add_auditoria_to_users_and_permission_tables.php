<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // users, roles y permissions ya tienen timestamps(); solo faltan el resto.
    private array $tablas = ['users', 'roles', 'permissions'];

    // passkeys y personal_access_tokens ya usan auditoria(); solo les falta estado.
    private array $tablasSoloEstado = ['passkeys', 'personal_access_tokens'];

    public function up(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->boolean('estado')->default(true);
                $table->unsignedBigInteger('creado_por')->nullable();
                $table->unsignedBigInteger('actualizado_por')->nullable();
                $table->softDeletes();
            });
        }

        foreach ($this->tablasSoloEstado as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->boolean('estado')->default(true));
        }
    }

    public function down(): void
    {
        foreach ($this->tablas as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropSoftDeletes();
                $table->dropColumn(['estado', 'creado_por', 'actualizado_por']);
            });
        }

        foreach ($this->tablasSoloEstado as $tabla) {
            Schema::table($tabla, fn (Blueprint $table) => $table->dropColumn('estado'));
        }
    }
};
