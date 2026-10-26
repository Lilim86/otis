<?php

namespace Database\Seeders;

use App\Http\Middleware\VerificaPermiso;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermisosSeeder extends Seeder
{
    /** Crea un permiso por cada operación de cada módulo (leídos de las rutas) y el rol Administrador con todos. */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $nombres = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => in_array('permiso', $ruta->gatherMiddleware(), true))
            ->flatMap(fn ($ruta) => collect($ruta->methods())->reject(fn ($m) => $m === 'HEAD')->map(
                fn ($metodo) => VerificaPermiso::permisoDe(Request::create('/'.$ruta->uri(), $metodo))
            ))->push('seguridad.administrar', 'portal.usar')->unique()->sort()->values();

        foreach ($nombres as $nombre) {
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        $this->rolesBase();

        User::where('username', 'admin')->first()?->assignRole($admin);
    }

    /** Perfiles por área del menú de SOFIA: cada uno puede operar sus módulos y solo consultar el resto. */
    private function rolesBase(): void
    {
        $areas = [
            'Planificación' => ['paises', 'departamentos', 'provincias', 'municipios', 'zonas', 'vias-acceso'],
            'Comercial' => ['empresas', 'edificios', 'contratos', 'cuotas-contrato', 'cobranzas', 'personas'],
            'Técnico' => ['visitas-mantenimiento', 'clasificadores-tecnicos', 'ascensores', 'equipos', 'mantenimientos', 'ordenes-mantenimiento', 'emergencias', 'repuestos', 'contratos-tecnicos'],
            'Almacenes' => ['almacenes', 'insumos', 'grupos-bienes', 'movimientos-almacen', 'compras', 'facturas-proveedor'],
            'Financiero' => ['bancos', 'cuentas-bancarias', 'comprobantes', 'pagos-clientes', 'ingresos-tesoreria', 'traspasos-cuentas', 'ordenes-cancelacion', 'fondos-rendir', 'rendiciones-fondo', 'cobranzas'],
            'Recursos Humanos' => ['cargos', 'profesiones', 'personal', 'planillas'],
        ];

        $consulta = Permission::where('name', 'like', '%.ver')->where('name', 'not like', 'seguridad.%')
            ->where('name', 'not like', 'usuarios.%')->where('name', 'not like', 'citas.%')->pluck('name');

        foreach ($areas as $nombre => $modulos) {
            $propios = Permission::where(fn ($q) => collect($modulos)->each(fn ($m) => $q->orWhere('name', 'like', "{$m}.%")))->pluck('name');
            Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'web'])->syncPermissions($propios->merge($consulta)->unique());
        }

        // Roles que no existen en el menú de SOFIA pero sí en la empresa: solo reciben lo que necesitan.
        $explicitos = [
            // Va a los edificios a cobrar mantenimientos y renovaciones de contrato.
            'Cobrador' => [
                'cobranzas.ver', 'cobranzas.crear', 'cobranzas.editar',
                'contratos.ver', 'cuotas-contrato.ver', 'edificios.ver', 'empresas.ver', 'personas.ver',
                'mantenimientos.ver', 'ordenes-mantenimiento.ver', 'cuentas-bancarias.ver', 'bancos.ver',
            ],
            // Agenda del gerente general, registro de visitas, consultas y llamadas de emergencia.
            'Secretaría' => [
                'citas.ver', 'citas.crear', 'citas.editar', 'citas.eliminar', 'citas.restaurar',
                'personas.ver', 'personas.crear', 'personas.editar',
                'empresas.ver', 'edificios.ver', 'contratos.ver', 'personal.ver',
                'emergencias.ver', 'emergencias.crear', 'ordenes-mantenimiento.ver', 'mantenimientos.ver',
            ],
        ];
        // Cliente: solo entra a su portal. No ve ningún módulo interno.
        $explicitos += ['Cliente' => ['portal.usar']];
        foreach ($explicitos as $nombre => $permisos) {
            Role::firstOrCreate(['name' => $nombre, 'guard_name' => 'web'])
                ->syncPermissions(Permission::whereIn('name', $permisos)->pluck('name'));
        }

        Role::firstOrCreate(['name' => 'Consulta', 'guard_name' => 'web'])->syncPermissions($consulta);
    }
}
