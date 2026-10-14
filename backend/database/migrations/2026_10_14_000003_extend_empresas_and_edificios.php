<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('tipo_documento', 10)->default('NIT');
            $table->string('expedido_en', 3)->nullable();
            $table->string('iniciales_sucursal', 10)->nullable();
            $table->date('fecha_creacion')->nullable();
            $table->string('telefono_fijo', 30)->nullable();
            $table->string('telefono_fax', 30)->nullable();
            $table->string('telefono_celular', 30)->nullable();
            $table->string('correo_contacto')->nullable();
            $table->string('pagina_web')->nullable();
            $table->ubicacion();
            $table->string('nro_vivienda', 20)->nullable();
            $table->string('nro_piso', 20)->nullable();
            $table->string('nro_depto', 20)->nullable();
            $table->aprobacion();
        });

        Schema::table('edificios', function (Blueprint $table) {
            $table->string('codigo_administrativo', 20)->nullable();
            $table->ubicacion();
            $table->string('nro_edificio', 20)->nullable();
            $table->string('referencia_cercana')->nullable();
            $table->foreignId('propietario_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('contacto_cobranzas_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->foreignId('contacto_tecnico_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->decimal('altura_msnm', 8, 2)->nullable();
            $table->aprobacion();
        });
    }

    public function down(): void
    {
        Schema::table('edificios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('propietario_id');
            $table->dropConstrainedForeignId('empresa_id');
            $table->dropConstrainedForeignId('contacto_cobranzas_id');
            $table->dropConstrainedForeignId('contacto_tecnico_id');
            foreach (['pais_id', 'departamento_id', 'provincia_id', 'municipio_id', 'zona_id', 'via_acceso_id'] as $c) {
                $table->dropConstrainedForeignId($c);
            }
            $table->dropColumn(['codigo_administrativo', 'nro_edificio', 'referencia_cercana', 'latitud', 'longitud', 'altura_msnm', 'estado_registro']);
        });

        Schema::table('empresas', function (Blueprint $table) {
            foreach (['pais_id', 'departamento_id', 'provincia_id', 'municipio_id', 'zona_id', 'via_acceso_id'] as $c) {
                $table->dropConstrainedForeignId($c);
            }
            $table->dropColumn(['tipo_documento', 'expedido_en', 'iniciales_sucursal', 'fecha_creacion', 'telefono_fijo', 'telefono_fax',
                'telefono_celular', 'correo_contacto', 'pagina_web', 'nro_vivienda', 'nro_piso', 'nro_depto', 'estado_registro']);
        });
    }
};
