<?php

namespace Database\Seeders;

use App\Models\ProductoCatalogo;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Catálogo tomado de cgibolivia.com.bo y de sus fichas PDF. Solo incluye datos publicados allí.
 * Se puede repetir: actualiza los productos y agrega permisos sin tocar los demás de cada rol.
 */
class CatalogoAscensoresSeeder extends Seeder
{
    private const SITIO = 'https://www.cgibolivia.com.bo/images/';

    public function run(): void
    {
        foreach ($this->productos() as $orden => $producto) {
            ProductoCatalogo::updateOrCreate(['codigo' => $producto['codigo']], [
                'categoria' => $producto['categoria'], 'nombre' => $producto['nombre'], 'descripcion' => $producto['descripcion'],
                'especificaciones' => collect($producto['especificaciones'])->map(fn ($valor, $etiqueta) => ['etiqueta' => $etiqueta, 'valor' => $valor])->values()->all(),
                'imagen_url' => self::SITIO.$producto['imagen'], 'catalogo_url' => $producto['catalogo'] ? self::SITIO.$producto['catalogo'] : null,
                'orden' => $orden + 1,
            ]);
        }

        $this->permisos();
    }

    private function permisos(): void
    {
        $permisos = collect(['pedidos-catalogo.ver', 'pedidos-catalogo.editar'])
            ->map(fn ($nombre) => Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']));

        Role::whereIn('name', ['Administrador', 'Comercial', 'Secretaría'])->get()->each->givePermissionTo($permisos);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<array{codigo: string, categoria: string, nombre: string, descripcion: string, especificaciones: array<string, string>, imagen: string, catalogo: ?string}> */
    private function productos(): array
    {
        return [
            [
                'codigo' => 'GEN2-LIGHT', 'categoria' => 'Ascensores sin sala de máquinas', 'nombre' => 'Gen2® Light',
                'descripcion' => 'Máquina compacta, con alto desempeño. Componentes basados en estándares mundiales de eficiencia energética.',
                'especificaciones' => [
                    'Capacidad de cabina' => '6 a 13 pasajeros',
                    'Velocidad' => '1,0 a 1,75 m/s',
                    'Distancia máxima entre paradas' => '12 m',
                    'Accionamiento' => 'Máquina con inversor de frecuencia VVVF',
                ],
                'imagen' => '2020/11/05/gen2lightplus2img.jpg', 'catalogo' => 'gen2light16spa.pdf',
            ],
            [
                'codigo' => 'GEN2-AMBIANCE', 'categoria' => 'Ascensores sin sala de máquinas', 'nombre' => 'Gen2® Ambiance',
                'descripcion' => 'Un ambiente más en su edificio: estética pura, simple e intuitiva, con iluminación sofisticada y materiales de calidad.',
                'especificaciones' => ['Opciones de diseño' => 'Natural y Modern'],
                'imagen' => '2020/11/05/genambiancecat.jpg', 'catalogo' => 'gen2ambiance25spa.pdf',
            ],
            [
                'codigo' => 'GEN2-MR', 'categoria' => 'Ascensores con sala de máquinas', 'nombre' => 'Gen2 MR',
                'descripcion' => 'Perfeccionando lo convencional: emplea una reducida sala de máquinas y asegura el mejor desempeño.',
                'especificaciones' => [
                    'Capacidad' => '630 a 1600 kg',
                    'Velocidad' => '1 a 2,5 m/s',
                    'Motor' => 'Sin engranajes',
                    'Consumo' => 'Sistema de voltaje bajo, desde 4.1 kW/h',
                    'Seguridad' => '3 sistemas anti-caídas',
                    'Ahorro de energía' => 'Sistema regenerativo, hasta 75 %',
                ],
                'imagen' => '2020/11/05/gen2mr.jpg', 'catalogo' => 'gen2mr.pdf',
            ],
            [
                'codigo' => 'PANORAMICO', 'categoria' => 'Ascensores panorámicos', 'nombre' => 'Ascensor panorámico',
                'descripcion' => 'Ascensores con diseños innovadores y modernos, cuyo elemento estético principal son los paneles acristalados, parciales o totales.',
                'especificaciones' => [
                    'Modelos de cabina' => 'G007, G020, G020A, G021, G022 y otros',
                    'Motor' => 'Sin engranajes',
                    'Sala de máquinas' => 'Sin cuarto de máquinas',
                    'Consumo' => 'Sistema de voltaje bajo, desde 7.2 kW/h',
                    'Seguridad' => '3 sistemas anti-caídas',
                    'Ahorro de energía' => 'Sistema regenerativo, hasta 75 %',
                ],
                'imagen' => '2020/11/05/asensorpanoramico.jpg', 'catalogo' => 'panoramicelevator.pdf',
            ],
            [
                'codigo' => 'MODULAR-CAB', 'categoria' => 'Cabinas estéticas', 'nombre' => 'Otis Modular Cab Aesthetic',
                'descripcion' => 'Diseños de cabina personalizados, con paneles planos y una amplia paleta de colores y materiales.',
                'especificaciones' => ['Personalización' => 'Paneles, pisos, techos y pasamanos'],
                'imagen' => '2020/11/05/estetica1.jpg', 'catalogo' => 'aesthetic.pdf',
            ],
            [
                'codigo' => 'XO508', 'categoria' => 'Escaleras mecánicas', 'nombre' => 'XO508',
                'descripcion' => 'Escaleras mecánicas que permiten transportar de 6 a 9 mil personas por hora. Los parámetros pueden variar según los requerimientos del cliente.',
                'especificaciones' => [
                    'Velocidad' => '0,5 m/s',
                    'Ancho' => '0,6 a 1 m',
                    'Inclinación' => '30° o 35°',
                    'Altura máxima' => '2,7 a 18 m',
                    'Consumo' => 'Sistema de bajo voltaje, desde 13 kW/h',
                    'Seguridad' => '3 sistemas anti-caídas',
                    'Ahorro de energía' => 'Sistema regenerativo, hasta 40 %',
                ],
                'imagen' => '2020/10/12/escaleramecach.jpg', 'catalogo' => 'xo508.pdf',
            ],
            [
                'codigo' => 'EH-500', 'categoria' => 'Montacargas', 'nombre' => 'Plataforma EH-500',
                'descripcion' => 'Construcción robusta con estructura-guía de perfiles laminados y rodaderas metálicas. Admite suelos de mayor superficie y habitáculo con barandilla, paramento o cabina.',
                'especificaciones' => [
                    'Carga' => '200 a 750 kg',
                    'Recorrido máximo' => '12 m (mayores, a consultar)',
                    'Velocidad' => '0,1 y 0,2 m/s',
                    'Superficie máxima' => '3,15 m²',
                    'Suspensión' => '2 cables o cadenas y 2 cables de seguridad',
                ],
                'imagen' => '2020/11/06/plataeh.jpg', 'catalogo' => 'eh.pdf',
            ],
            [
                'codigo' => 'HO', 'categoria' => 'Montacargas', 'nombre' => 'Plataforma HO',
                'descripcion' => 'Guías calibradas y soldadas a un chasis, con buen aprovechamiento del hueco.',
                'especificaciones' => [
                    'Carga' => '100 a 500 kg',
                    'Recorrido máximo' => '12 m (mayores, a consultar)',
                    'Velocidad' => '0,2 y 0,3 m/s',
                    'Superficie máxima' => '2 m²',
                    'Foso mínimo' => '120 mm',
                    'Suspensión' => 'Por cadenas, con paracaídas contra rotura',
                ],
                'imagen' => '2020/11/06/plataeh.jpg', 'catalogo' => 'eh.pdf',
            ],
            [
                'codigo' => 'FL590-ECH', 'categoria' => 'Montacoches', 'nombre' => 'FL590 Montacoches ECH',
                'descripcion' => 'Traslado vertical de vehículos con sus ocupantes, para garajes donde no es posible construir una rampa o para sustituir un montacoches existente.',
                'especificaciones' => [
                    'Carga' => '2000, 2500, 3000 y 3500 kg',
                    'Superficie' => '10 a 17,5 m²',
                    'Recorrido máximo' => '7 m (mayores, a consultar)',
                    'Paradas' => '2 a 3',
                    'Velocidad' => '0,10 m/s',
                    'Foso' => '400 mm',
                    'Huida' => '2700 mm',
                    'Estructura' => 'Doble columna, ancho de hasta 3 m',
                ],
                'imagen' => '2020/11/06/montaauto.jpg', 'catalogo' => 'ech.pdf',
            ],
            [
                'codigo' => 'CT405-QHV', 'categoria' => 'Montacoches', 'nombre' => 'CT405 Montacoches QHV',
                'descripcion' => 'Para edificios de viviendas, oficinas y parking público donde el aprovechamiento del espacio es indispensable. Incluye centrado de vehículos.',
                'especificaciones' => [
                    'Carga' => '3500, 4000, 4500 y 5000 kg',
                    'Recorrido máximo' => '21 m, máx. 4 paradas (mayores, a consultar)',
                    'Ancho de cabina' => '2400 a 3000 mm',
                    'Fondo de cabina' => '5000 a 6000 mm',
                    'Altura de cabina' => '2200 a 2800 mm',
                    'Velocidad' => '0,2 a 0,6 m/s',
                    'Embarque' => 'Simple o doble (180°)',
                ],
                'imagen' => '2020/11/06/montaauto2.jpg', 'catalogo' => 'qhv.pdf',
            ],
            [
                'codigo' => 'MH', 'categoria' => 'Minicargas', 'nombre' => 'Minicarga MH',
                'descripcion' => 'Transporte vertical de pequeñas cargas en restaurantes, hoteles, oficinas, bibliotecas, almacenes y hospitales.',
                'especificaciones' => [
                    'Carga' => '50 a 100 kg',
                    'Velocidad' => '0,35 m/s',
                    'Recorrido' => 'Hidráulico hasta 8 m; eléctrico hasta 35 m',
                    'Paradas' => 'Hasta 12',
                    'Acabados' => 'Acero inoxidable o chapa pintada',
                ],
                'imagen' => '2020/11/06/minicargaprod.jpg', 'catalogo' => 'minicargas.pdf',
            ],
            [
                'codigo' => 'MTH', 'categoria' => 'Minicargas', 'nombre' => 'Minicarga MTH',
                'descripcion' => 'Montacargas de carga media para pequeños carros o carretillas manuales, con nivel de servicio enrasado con el suelo.',
                'especificaciones' => [
                    'Carga' => 'MTH 300: 200 y 300 kg; MTH 500: 400 y 500 kg',
                    'Velocidad' => 'Hidráulico 0,2 o 0,3 m/s; eléctrico 0,3 m/s',
                    'Recorrido' => 'Hidráulico hasta 12 m; eléctrico hasta 35 m',
                    'Paradas' => 'Hasta 12',
                    'Acabados' => 'Acero inoxidable o chapa pintada',
                ],
                'imagen' => '2020/11/06/minicargaprod.jpg', 'catalogo' => 'minicargas.pdf',
            ],
        ];
    }
}
