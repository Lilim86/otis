<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Bienes y Servicios" del SOFIA: se amplía la tabla de insumos existente.
        Schema::table('insumos', function (Blueprint $table) {
            $table->foreignId('grupo_id')->nullable()->constrained('grupos_bienes')->nullOnDelete();
            $table->foreignId('subgrupo1_id')->nullable()->constrained('grupos_bienes')->nullOnDelete();
            $table->foreignId('subgrupo2_id')->nullable()->constrained('grupos_bienes')->nullOnDelete();
            $table->text('caracteristicas')->nullable();
            $table->string('unidad_medida', 10)->nullable();
            $table->string('industria', 10)->nullable(); // país de origen, ej. BRA, BOL
            $table->string('marca', 60)->nullable();
            $table->string('modelo', 60)->nullable();
            $table->string('codigo_referencia', 40)->nullable();
            $table->string('rotacion', 15)->default('NO_APROBADO');
            $table->decimal('stock_minimo', 12, 2)->default(0);
            $table->decimal('precio_compra_bs', 12, 2)->default(0);
            $table->decimal('precio_compra_usd', 12, 2)->default(0);
            $table->decimal('precio_venta_base_bs', 12, 2)->default(0);
            $table->decimal('precio_venta_base_usd', 12, 2)->default(0);
            $table->decimal('precio_venta_cliente_bs', 12, 2)->default(0);
            $table->decimal('precio_venta_cliente_usd', 12, 2)->default(0);
            $table->string('tipo_almacen', 1)->nullable();
            $table->string('codigo_sin', 20)->nullable(); // Servicio de Impuestos Nacionales
            $table->string('descripcion_sin')->nullable();
            $table->aprobacion();
        });

        Schema::table('equipos', function (Blueprint $table) {
            $table->foreignId('grupo_id')->nullable()->constrained('grupos_bienes')->nullOnDelete();
            $table->foreignId('subgrupo1_id')->nullable()->constrained('grupos_bienes')->nullOnDelete();
            $table->foreignId('subgrupo2_id')->nullable()->constrained('grupos_bienes')->nullOnDelete();
            $table->text('caracteristicas')->nullable();
            $table->string('unidad_medida', 10)->nullable();
            $table->string('codigo_referencia', 40)->nullable();
            $table->string('rotacion', 15)->default('NO_APROBADO');
            $table->decimal('precio_compra_fob_bs', 12, 2)->default(0);
            $table->decimal('precio_compra_fob_usd', 12, 2)->default(0);
            $table->decimal('precio_venta_base_bs', 12, 2)->default(0);
            $table->decimal('precio_venta_base_usd', 12, 2)->default(0);
            $table->decimal('precio_venta_cliente_bs', 12, 2)->default(0);
            $table->decimal('precio_venta_cliente_usd', 12, 2)->default(0);
            $table->decimal('horas_servicio', 8, 2)->nullable(); // Horas de servicio (cronograma)
            $table->aprobacion();
        });

        // Insumos básicos de cada equipo para su cronograma de mantenimiento.
        Schema::create('equipo_insumos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('equipos')->cascadeOnDelete();
            $table->foreignId('insumo_id')->constrained('insumos')->cascadeOnDelete();
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->timestamps();
            $table->unique(['equipo_id', 'insumo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipo_insumos');

        Schema::table('equipos', function (Blueprint $table) {
            foreach (['grupo_id', 'subgrupo1_id', 'subgrupo2_id'] as $c) {
                $table->dropConstrainedForeignId($c);
            }
            $table->dropColumn(['caracteristicas', 'unidad_medida', 'codigo_referencia', 'rotacion', 'precio_compra_fob_bs', 'precio_compra_fob_usd',
                'precio_venta_base_bs', 'precio_venta_base_usd', 'precio_venta_cliente_bs', 'precio_venta_cliente_usd', 'horas_servicio', 'estado_registro']);
        });

        Schema::table('insumos', function (Blueprint $table) {
            foreach (['grupo_id', 'subgrupo1_id', 'subgrupo2_id'] as $c) {
                $table->dropConstrainedForeignId($c);
            }
            $table->dropColumn(['caracteristicas', 'unidad_medida', 'industria', 'marca', 'modelo', 'codigo_referencia', 'rotacion', 'stock_minimo',
                'precio_compra_bs', 'precio_compra_usd', 'precio_venta_base_bs', 'precio_venta_base_usd', 'precio_venta_cliente_bs',
                'precio_venta_cliente_usd', 'tipo_almacen', 'codigo_sin', 'descripcion_sin', 'estado_registro']);
        });
    }
};
