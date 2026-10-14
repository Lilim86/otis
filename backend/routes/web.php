<?php

use App\Http\Controllers\ReporteCombinadoController;
use App\Http\Controllers\ReporteController;
use App\Livewire\DashboardPrincipal;
use App\Livewire\GestionAgenda;
use App\Livewire\GestionBancos;
use App\Livewire\GestionCargos;
use App\Livewire\GestionClasificadoresTecnicos;
use App\Livewire\GestionCobranzas;
use App\Livewire\GestionContratos;
use App\Livewire\GestionCuentasBancarias;
use App\Livewire\GestionDepartamentos;
use App\Livewire\GestionEdificios;
use App\Livewire\GestionEmergencias;
use App\Livewire\GestionEmpresas;
use App\Livewire\GestionEquipos;
use App\Livewire\GestionInsumos;
use App\Livewire\GestionMantenimiento;
use App\Livewire\GestionMunicipios;
use App\Livewire\GestionPaises;
use App\Livewire\GestionPersonal;
use App\Livewire\GestionProfesiones;
use App\Livewire\GestionProvincias;
use App\Livewire\GestionReportes;
use App\Livewire\GestionRepuestos;
use App\Livewire\GestionUsuarios;
use App\Livewire\GestionViasAcceso;
use App\Livewire\GestionZonas;

// Pantalla de bienvenida (video de la empresa) para quien aún no inicia sesión.
Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('inicio'))->name('inicio');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', DashboardPrincipal::class)
        ->middleware(['auth', 'verified'])
        ->name('dashboard');

    Route::get('/financiero/cobranzas', GestionCobranzas::class)->middleware('modulo:cobranzas')->name('financiero.cobranzas');

    Route::get('/seguridad/usuarios', GestionUsuarios::class)->middleware('can:administrar-seguridad')->name('seguridad.usuarios');

    Route::get('/reportes/kardex', [ReporteController::class, 'kardexPdf'])->middleware('modulo:insumos')->name('reportes.kardex');

    // REPORTES COMBINADOS (cada reporte exige ver el módulo al que pertenece)
    Route::get('/reportes', GestionReportes::class)->name('reportes.index');
    Route::get('/reportes/{clave}/{formato}', [ReporteCombinadoController::class, 'ver'])->where('formato', 'pdf|csv|json')->name('reportes.descargar');

    // SECRETARÍA
    Route::get('/secretaria/agenda', GestionAgenda::class)->middleware('modulo:citas')->name('agenda.index');

    // MÓDULO DE PLANIFICACIÓN (Geografía)
    Route::get('/planificacion/paises', GestionPaises::class)->middleware('modulo:paises')->name('paises.index');
    Route::get('/planificacion/departamentos', GestionDepartamentos::class)->middleware('modulo:departamentos')->name('departamentos.index');
    Route::get('/planificacion/provincias', GestionProvincias::class)->middleware('modulo:provincias')->name('provincias.index');
    Route::get('/planificacion/municipios', GestionMunicipios::class)->middleware('modulo:municipios')->name('municipios.index');
    Route::get('/planificacion/zonas', GestionZonas::class)->middleware('modulo:zonas')->name('zonas.index');
    Route::get('/planificacion/vias-acceso', GestionViasAcceso::class)->middleware('modulo:vias-acceso')->name('vias-acceso.index');

    // MÓDULO TÉCNICO (Operaciones OTIS)
    Route::get('/tecnico/clasificadores', GestionClasificadoresTecnicos::class)->middleware('modulo:clasificadores-tecnicos')->name('tecnico.clasificadores');
    Route::get('/tecnico/mantenimiento', GestionMantenimiento::class)->middleware('modulo:mantenimientos,ordenes-mantenimiento')->name('tecnico.mantenimiento');
    Route::get('/tecnico/emergencias', GestionEmergencias::class)->middleware('modulo:emergencias')->name('tecnico.emergencias');
    Route::get('/tecnico/repuestos', GestionRepuestos::class)->middleware('modulo:repuestos')->name('tecnico.repuestos');
    Route::get('/tecnico/equipos', GestionEquipos::class)->middleware('modulo:equipos')->name('tecnico.equipos');

    // MÓDULO DE RECURSOS HUMANOS
    Route::get('/rrhh/cargos', GestionCargos::class)->middleware('modulo:cargos')->name('rrhh.cargos');
    Route::get('/rrhh/profesiones', GestionProfesiones::class)->middleware('modulo:profesiones')->name('rrhh.profesiones');
    Route::get('/rrhh/personal', GestionPersonal::class)->middleware('modulo:personal')->name('rrhh.personal');

    // MÓDULO COMERCIAL
    Route::get('/comercial/empresas', GestionEmpresas::class)->middleware('modulo:empresas')->name('comercial.empresas');
    Route::get('/comercial/edificios', GestionEdificios::class)->middleware('modulo:edificios')->name('comercial.edificios');
    Route::get('/comercial/contratos', GestionContratos::class)->middleware('modulo:contratos')->name('comercial.contratos');

    // MÓDULO DE ALMACENES
    Route::get('/almacenes/insumos', GestionInsumos::class)->middleware('modulo:insumos')->name('almacenes.insumos');

    // MÓDULO FINANCIERO
    Route::get('/financiero/bancos', GestionBancos::class)->middleware('modulo:bancos')->name('financiero.bancos');
    Route::get('/financiero/cuentas', GestionCuentasBancarias::class)->middleware('modulo:cuentas-bancarias')->name('financiero.cuentas');
});
