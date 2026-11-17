<?php

use App\Http\Controllers\Api\AccesoController;
use App\Http\Controllers\Api\AlmacenController;
use App\Http\Controllers\Api\AscensorController;
use App\Http\Controllers\Api\BancoController;
use App\Http\Controllers\Api\CargoController;
use App\Http\Controllers\Api\CatalogoController;
use App\Http\Controllers\Api\CitaController;
use App\Http\Controllers\Api\ClasificadorTecnicoController;
use App\Http\Controllers\Api\CobradorController;
use App\Http\Controllers\Api\CobranzaController;
use App\Http\Controllers\Api\CobrosCobradoresController;
use App\Http\Controllers\Api\CompraController;
use App\Http\Controllers\Api\ComprobanteController;
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
use App\Http\Controllers\Api\IngenieriaController;
use App\Http\Controllers\Api\IngresoTesoreriaController;
use App\Http\Controllers\Api\InsumoController;
use App\Http\Controllers\Api\MantenimientoController;
use App\Http\Controllers\Api\MovimientoAlmacenController;
use App\Http\Controllers\Api\MunicipioController;
use App\Http\Controllers\Api\OrdenCancelacionController;
use App\Http\Controllers\Api\OrdenMantenimientoController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\PagoClienteController;
use App\Http\Controllers\Api\PaisController;
use App\Http\Controllers\Api\PersonaController;
use App\Http\Controllers\Api\PersonalController;
use App\Http\Controllers\Api\PlanillaController;
use App\Http\Controllers\Api\PedidoCatalogoController;
use App\Http\Controllers\Api\PortalCatalogoController;
use App\Http\Controllers\Api\PortalClienteController;
use App\Http\Controllers\Api\ProfesionController;
use App\Http\Controllers\Api\ProvinciaController;
use App\Http\Controllers\Api\QrCobroController;
use App\Http\Controllers\Api\RendicionFondoController;
use App\Http\Controllers\Api\RepuestoController;
use App\Http\Controllers\Api\RolController;
use App\Http\Controllers\Api\TecnicoController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\TraspasoCuentaController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\ViaAccesoController;
use App\Http\Controllers\Api\VisitaMantenimientoController;
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
        'es_cliente' => $request->user()->empresa_id !== null,
        'es_tecnico' => $request->user()->personal_id !== null,
        'es_cobrador' => $request->user()->esCobrador(),
        'empresa' => $request->user()->empresa?->only(['id', 'denominacion']),
    ]);

    // Vista del técnico: solo sus visitas.
    Route::middleware('tecnico')->prefix('tecnico')->group(function () {
        Route::get('ruta', [TecnicoController::class, 'ruta']);
        Route::post('visitas/{id}/iniciar', [TecnicoController::class, 'iniciar']);
        Route::post('visitas/{id}/completar', [TecnicoController::class, 'completar']);
        Route::get('materiales', [TecnicoController::class, 'materiales']);
        Route::get('herramientas', [TecnicoController::class, 'herramientas']);
    });

    // Portal del cobrador: su ruta por los edificios que tiene asignados, sus cobros y la rendición a Tesorería.
    Route::middleware('cobrador')->prefix('cobrador')->group(function () {
        Route::get('ruta', [CobradorController::class, 'ruta']);
        Route::get('cobros', [CobradorController::class, 'cobros']);
        Route::post('cobros', [CobradorController::class, 'registrar'])->middleware('throttle:60,1');
        Route::post('cobros/enviar', [CobradorController::class, 'enviar'])->middleware('throttle:20,1');
        Route::put('cobros/{id}', [CobradorController::class, 'actualizar'])->whereNumber('id');
        Route::delete('cobros/{id}', [CobradorController::class, 'eliminar'])->whereNumber('id');
        Route::get('cobros/{id}/{tipo}', [CobradorController::class, 'comprobante'])->whereNumber('id');
    });

    // Facturas y recibos: ver, enviar por correo y modelos de diseño.
    Route::middleware('permiso')->prefix('comprobantes')->group(function () {
        Route::get('modelo/{tipo}', [ComprobanteController::class, 'modelo']);
        Route::get('cobranzas/{id}/envios', [ComprobanteController::class, 'envios']);
        Route::post('cobranzas/{id}/enviar', [ComprobanteController::class, 'enviar'])->middleware('throttle:20,1');
        Route::get('cobranzas/{id}/{tipo}', [ComprobanteController::class, 'ver']);
    });

    // Portal del cliente: cada empresa ve y hace solo lo suyo.
    Route::middleware('cliente')->prefix('portal')->group(function () {
        Route::get('resumen', [PortalClienteController::class, 'resumen']);
        Route::get('contratos', [PortalClienteController::class, 'contratosIndex']);
        Route::get('equipos', [PortalClienteController::class, 'equipos']);
        Route::get('mantenimientos', [PortalClienteController::class, 'mantenimientos']);
        Route::post('mantenimientos/solicitar', [PortalClienteController::class, 'solicitar']);
        Route::post('mantenimientos/{id}/calificar', [PortalClienteController::class, 'calificar']);
        Route::get('cronograma', [PortalClienteController::class, 'cronograma']);
        Route::get('cuotas', [PortalClienteController::class, 'cuotas']);
        Route::get('pagos/cuenta', [PortalClienteController::class, 'datosCuenta']);
        Route::get('pagos', [PortalClienteController::class, 'pagos']);
        Route::post('pagos', [PortalClienteController::class, 'pagar'])->middleware('throttle:20,1');
        Route::get('pagos/{id}/comprobante', [PortalClienteController::class, 'comprobantePago']);
        Route::get('pagos/{id}/recibo', [PortalClienteController::class, 'reciboPago']);
        Route::get('mantenimientos/{id}/informe', [PortalClienteController::class, 'informeVisita']);
        Route::post('mantenimientos/{id}/firmar', [PortalClienteController::class, 'firmar'])->middleware('throttle:20,1');
        Route::get('catalogo', [PortalCatalogoController::class, 'catalogo']);
        Route::get('pedidos', [PortalCatalogoController::class, 'pedidos']);
        Route::post('pedidos', [PortalCatalogoController::class, 'pedir'])->middleware('throttle:20,1');
    });

    // Pedidos del catálogo: los crea el cliente desde su portal; la empresa solo los consulta y atiende.
    Route::middleware('permiso')->group(function () {
        Route::get('pedidos-catalogo/papelera', [PedidoCatalogoController::class, 'papelera']);
        Route::apiResource('pedidos-catalogo', PedidoCatalogoController::class)->only(['index', 'show', 'update'])->names('api.pedidos-catalogo');
    });

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

    // Aportes de ingeniería: rutas, asignación, confiabilidad y nivel de servicio.
    Route::middleware('permiso')->prefix('ingenieria')->group(function () {
        Route::get('resumen', [IngenieriaController::class, 'resumen']);
        Route::get('rutas', [IngenieriaController::class, 'rutas']);
        Route::post('rutas/aplicar', [IngenieriaController::class, 'aplicarRutas']);
        Route::get('asignacion', [IngenieriaController::class, 'compararAsignacion']);
        Route::post('asignacion/aplicar', [IngenieriaController::class, 'asignar']);
        Route::get('confiabilidad', [IngenieriaController::class, 'confiabilidad']);
        Route::post('confiabilidad/{edificio}/adelantar', [IngenieriaController::class, 'adelantar'])->whereNumber('edificio');
        Route::get('servicio', [IngenieriaController::class, 'servicio']);
        Route::post('clasificar-falla', [IngenieriaController::class, 'clasificar']);
    });

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
    // Avisos al gerente de las citas que agenda Secretaría (antes del CRUD para que no se confunda con un {id}).
    Route::get('citas/notificaciones', [CitaController::class, 'notificaciones'])->middleware('permiso');
    Route::post('citas/{id}/visto', [CitaController::class, 'marcarVista'])->middleware('permiso');
    $modulo('citas', CitaController::class);
    Route::get('visitas-mantenimiento/tablero', [VisitaMantenimientoController::class, 'tablero'])->middleware('permiso');
    Route::post('visitas-mantenimiento/autoasignar', [VisitaMantenimientoController::class, 'autoasignar'])->middleware('permiso');
    Route::post('visitas-mantenimiento/generar', [VisitaMantenimientoController::class, 'generar'])->middleware('permiso');
    Route::get('visitas-mantenimiento/{id}/informe', [VisitaMantenimientoController::class, 'informe'])->middleware('permiso');
    Route::get('pagos-clientes/{id}/comprobante', [PagoClienteController::class, 'comprobante'])->middleware('permiso');
    $modulo('visitas-mantenimiento', VisitaMantenimientoController::class);
    $modulo('pagos-clientes', PagoClienteController::class, true);
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
    Route::get('movimientos-almacen/herramientas-en-poder', [MovimientoAlmacenController::class, 'herramientasEnPoder'])->middleware('permiso');
    Route::get('movimientos-almacen/existencias/{insumoId}', [MovimientoAlmacenController::class, 'existencias'])->middleware('permiso');
    $modulo('movimientos-almacen', MovimientoAlmacenController::class, true);

    // Compras
    $modulo('compras', CompraController::class, true);
    $modulo('facturas-proveedor', FacturaProveedorController::class, true);
    $modulo('ordenes-cancelacion', OrdenCancelacionController::class, true);

    // Financiero
    $modulo('bancos', BancoController::class);
    $modulo('cuentas-bancarias', CuentaBancariaController::class);
    // Analítica de cobranza (riesgo de mora y flujo de caja): la ve quien puede ver cobranzas.
    Route::get('cobranzas/analisis', [IngenieriaController::class, 'cobranza'])->middleware('permiso');
    // QR de cobro: todos lo ven (el cliente en su portal); solo Cobranzas lo cambia.
    Route::get('pagos-qr', [QrCobroController::class, 'datos']);
    Route::get('pagos-qr/imagen', [QrCobroController::class, 'imagen']);
    Route::post('cobranzas/qr', [QrCobroController::class, 'guardar'])->middleware(['permiso', 'throttle:20,1']);
    Route::delete('cobranzas/qr', [QrCobroController::class, 'restablecer'])->middleware('permiso');
    $modulo('cobranzas', CobranzaController::class, true);

    // Tesorería
    // Cobros que mandan los cobradores: Tesorería los aprueba u observa (antes del CRUD para que no se confunda con un {id}).
    Route::middleware('permiso')->prefix('ingresos-tesoreria/cobradores')->group(function () {
        Route::get('/', [CobrosCobradoresController::class, 'index']);
        Route::post('{id}/aprobar', [CobrosCobradoresController::class, 'aprobar'])->whereNumber('id');
        Route::post('{id}/observar', [CobrosCobradoresController::class, 'observar'])->whereNumber('id');
    });
    $modulo('ingresos-tesoreria', IngresoTesoreriaController::class, true);
    $modulo('traspasos-cuentas', TraspasoCuentaController::class, true);
    Route::get('tesoreria/conciliacion', ConciliacionController::class);

    // Fondos a rendir
    $modulo('fondos-rendir', FondoRendirController::class, true);
    $modulo('rendiciones-fondo', RendicionFondoController::class, true);
});
