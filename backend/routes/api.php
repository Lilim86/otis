<?php

use App\Http\Controllers\Api\AccesoController;
use App\Http\Controllers\Api\AlmacenController;
use App\Http\Controllers\Api\AscensorController;
use App\Http\Controllers\Api\BancoController;
use App\Http\Controllers\Api\CargoController;
use App\Http\Controllers\Api\CatalogoController;
use App\Http\Controllers\Api\CitaController;
use App\Http\Controllers\Api\ClasificadorTecnicoController;
use App\Http\Controllers\Api\CobranzaController;
use App\Http\Controllers\Api\CompraController;
use App\Http\Controllers\Api\ConciliacionController;
use App\Http\Controllers\Api\ContratoController;
use App\Http\Controllers\Api\ContratoTecnicoController;
use App\Http\Controllers\Api\CuentaBancariaController;
use App\Http\Controllers\Api\CuotaContratoController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartamentoController;
use App\Http\Controllers\Api\EdificioController;
use App\Http\Controllers\Api\EmergenciaController;
use App\Http\Controllers\Api\EmpresaController;
use App\Http\Controllers\Api\EquipoController;
use App\Http\Controllers\Api\FacturaProveedorController;
use App\Http\Controllers\Api\FondoRendirController;
use App\Http\Controllers\Api\GrupoBienController;
use App\Http\Controllers\Api\InformeController;
use App\Http\Controllers\Api\IngresoTesoreriaController;
use App\Http\Controllers\Api\InsumoController;
use App\Http\Controllers\Api\MantenimientoController;
use App\Http\Controllers\Api\MovimientoAlmacenController;
use App\Http\Controllers\Api\MunicipioController;
use App\Http\Controllers\Api\OrdenCancelacionController;
use App\Http\Controllers\Api\OrdenMantenimientoController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\PaisController;
use App\Http\Controllers\Api\PersonaController;
use App\Http\Controllers\Api\PersonalController;
use App\Http\Controllers\Api\PlanillaController;
use App\Http\Controllers\Api\ProfesionController;
use App\Http\Controllers\Api\ProvinciaController;
use App\Http\Controllers\Api\RendicionFondoController;
use App\Http\Controllers\Api\RepuestoController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\TraspasoCuentaController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\ViaAccesoController;
use App\Http\Controllers\Api\ZonaController;
use App\Http\Controllers\ReporteCombinadoController;
use App\Http\Controllers\ReporteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('auth/otp/solicitar', [OtpController::class, 'solicitar'])->middleware('throttle:6,1');
Route::post('auth/otp/verificar', [OtpController::class, 'verificar'])->middleware('throttle:10,1');
Route::post('auth/token', [TokenController::class, 'store'])->middleware('throttle:10,1');

// Rutas comunes de cada módulo: papelera, restaurar, alta, baja y el CRUD.
// Las de papelera van antes del CRUD para que /papelera no se confunda con un {id}.
$modulo = function (string $uri, string $controlador, bool $aprobacion = false): void {
    Route::middleware('permiso')->group(function () use ($uri, $controlador, $aprobacion) {
        Route::get("{$uri}/papelera", [$controlador, 'papelera']);
        Route::post("{$uri}/{id}/restaurar", [$controlador, 'restaurar']);
        Route::post("{$uri}/{id}/alta", [$controlador, 'alta']);
        Route::post("{$uri}/{id}/baja", [$controlador, 'baja']);
        if ($aprobacion) {
            Route::post("{$uri}/{id}/aprobar", [$controlador, 'aprobar']);
            Route::post("{$uri}/{id}/anular", [$controlador, 'anular']);
        }
        Route::apiResource($uri, $controlador)->names("api.{$uri}");
    });
};

Route::middleware('auth:sanctum')->group(function () use ($modulo) {
    Route::delete('auth/token', [TokenController::class, 'destroy']);

    Route::get('/user', fn (Request $request) => $request->user()->toArray() + [
        'roles' => $request->user()->getRoleNames(),
        'permisos' => $request->user()->getAllPermissions()->pluck('name')->values(),
        'es_super_admin' => $request->user()->id === 1,
        'permisos_activos' => (bool) config('sofia.permisos_activos'),
    ]);

    Route::get('catalogos', [CatalogoController::class, 'index']);
    Route::get('dashboard/resumen', [DashboardController::class, 'resumen']);
    Route::get('reportes/seguimiento', [InformeController::class, 'seguimiento']);
    Route::get('reportes/cobranzas', [InformeController::class, 'cobranzas']);
    Route::get('reportes/inventario', [InformeController::class, 'inventario']);
    Route::get('alertas', [InformeController::class, 'alertas']);
    Route::get('reportes/kardex', [ReporteController::class, 'kardexPdf']);
    // Reportes combinados de tablas, según los módulos que el rol puede ver.
    Route::get('reportes/combinados', [ReporteCombinadoController::class, 'catalogo']);
    Route::get('reportes/combinados/{clave}', [ReporteCombinadoController::class, 'ver']);

    // Seguridad: control de accesos y privilegios (siempre solo para quien administra la seguridad)
    Route::middleware('can:administrar-seguridad')->prefix('seguridad')->group(function () {
        Route::get('accesos', [AccesoController::class, 'index']);
        Route::get('permisos', [RolController::class, 'permisos']);
        Route::apiResource('roles', RolController::class)->names('api.roles');
        Route::put('usuarios/{usuario}/roles', [RolController::class, 'asignar']);
    });

    // Seguridad
    $modulo('usuarios', UsuarioController::class);

    // Planificación (geografía)
    $modulo('paises', PaisController::class);
    $modulo('departamentos', DepartamentoController::class);
    $modulo('provincias', ProvinciaController::class);
    $modulo('municipios', MunicipioController::class);
    $modulo('zonas', ZonaController::class);
    $modulo('vias-acceso', ViaAccesoController::class);

    // Técnico
    $modulo('clasificadores-tecnicos', ClasificadorTecnicoController::class);
    $modulo('mantenimientos', MantenimientoController::class);
    $modulo('ascensores', AscensorController::class);
    $modulo('emergencias', EmergenciaController::class);
    Route::post('equipos/calcular-precios', [EquipoController::class, 'calcularPrecios']);
    $modulo('equipos', EquipoController::class, true);
    $modulo('grupos-bienes', GrupoBienController::class);
    $modulo('repuestos', RepuestoController::class);
    Route::post('ordenes-mantenimiento/{id}/finalizar', [OrdenMantenimientoController::class, 'finalizar']);
    $modulo('ordenes-mantenimiento', OrdenMantenimientoController::class);

    // Recursos humanos
    $modulo('cargos', CargoController::class);
    $modulo('citas', CitaController::class);
    $modulo('profesiones', ProfesionController::class);
    $modulo('personal', PersonalController::class);
    $modulo('personas', PersonaController::class, true);
    $modulo('planillas', PlanillaController::class, true);
    $modulo('contratos-tecnicos', ContratoTecnicoController::class, true);

    // Comercial
    $modulo('empresas', EmpresaController::class, true);
    $modulo('edificios', EdificioController::class, true);
    $modulo('contratos', ContratoController::class, true);
    $modulo('cuotas-contrato', CuotaContratoController::class);

    // Almacenes
    $modulo('insumos', InsumoController::class, true);
    $modulo('almacenes', AlmacenController::class);
    Route::get('almacenes/{id}/inventario', [AlmacenController::class, 'inventario']);
    $modulo('movimientos-almacen', MovimientoAlmacenController::class, true);

    // Compras
    $modulo('compras', CompraController::class, true);
    $modulo('facturas-proveedor', FacturaProveedorController::class, true);
    $modulo('ordenes-cancelacion', OrdenCancelacionController::class, true);

    // Financiero
    $modulo('bancos', BancoController::class);
    $modulo('cuentas-bancarias', CuentaBancariaController::class);
    $modulo('cobranzas', CobranzaController::class, true);

    // Tesorería
    $modulo('ingresos-tesoreria', IngresoTesoreriaController::class, true);
    $modulo('traspasos-cuentas', TraspasoCuentaController::class, true);
    Route::get('tesoreria/conciliacion', ConciliacionController::class);

    // Fondos a rendir
    $modulo('fondos-rendir', FondoRendirController::class, true);
    $modulo('rendiciones-fondo', RendicionFondoController::class, true);
});
