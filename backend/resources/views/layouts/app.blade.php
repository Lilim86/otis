<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Sistema OTIS</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased bg-marfil flex h-screen overflow-hidden">

        <!-- SIDEBAR -->
        <aside class="w-64 h-full bg-gradient-to-b from-marca-950 via-marca-900 to-marca-800 text-white flex flex-col shadow-2xl z-20 border-r border-oro-500/30">
            <!-- Título -->
            <div class="h-20 flex flex-col items-center justify-center border-b border-oro-500/40 shrink-0">
                <h2 class="font-display text-3xl font-black tracking-[0.35em] text-oro-300">OTIS</h2>
                <span class="text-[10px] uppercase tracking-[0.4em] text-oro-200/70">LILIM</span>
            </div>
            
            <!-- Menú (Aquí ocurre la magia del Scroll) -->
            <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
                <a href="{{ route('dashboard') }}" class="block px-4 py-2 rounded-lg bg-oro-500/15 border border-oro-500/30 hover:bg-oro-500/25 text-oro-100 font-medium transition flex items-center gap-2">
                    📊 Tablero Principal
                </a>
                
                <div class="pt-6 pb-2 text-xs font-semibold text-oro-400/80 uppercase tracking-[0.25em]">Módulos</div>

                @can('administrar-seguridad')
                <!-- Módulo de Seguridad -->
                <div x-data="{ open: false }" class="mb-2">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 hover:bg-white/10 transition-colors rounded-lg">
                        <div class="flex items-center">
                            <span>🔐</span>
                            <span class="ml-3 font-semibold text-sm">Seguridad del Sistema</span>
                        </div>
                        <svg :class="{'rotate-180': open}" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="open" x-collapse class="pl-11 pr-4 py-2 space-y-2 bg-marca-950/60 rounded-b-lg">
                        <a href="{{ route('seguridad.usuarios') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Administración de Usuarios</a>
                    </div>
                </div>
                @endcan

@can('modulo', ['citas'])
<!-- Módulo Secretaría (agenda del gerente general) -->
                <div x-data="{ open: false }" class="mb-2">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 hover:bg-white/10 transition-colors rounded-lg">
                        <div class="flex items-center">
                            <span>🗓️</span>
                            <span class="ml-3 font-semibold text-sm">Secretaría</span>
                        </div>
                        <svg :class="{'rotate-180': open}" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="open" x-collapse class="pl-11 pr-4 py-2 space-y-2 bg-marca-950/60 rounded-b-lg">
                        @can('modulo', ['citas'])
                        <a href="{{ route('agenda.index') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Agenda y Citas</a>
                        @endcan
                    </div>
                </div>
@endcan
@can('modulo', ['paises', 'departamentos', 'provincias', 'municipios', 'zonas', 'vias-acceso'])
@can('modulo', \App\Services\ReporteCombinadoService::todosLosModulos())
                <a href="{{ route('reportes.index') }}" class="block px-4 py-3 mb-2 hover:bg-white/10 transition-colors rounded-lg font-semibold text-sm"><span>🖨️</span><span class="ml-3">Reportes</span></a>
                @endcan

<!-- Módulo Geográfico Desplegable -->
                <div x-data="{ open: false }" class="mb-2">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 hover:bg-white/10 transition-colors rounded-lg">
                        <div class="flex items-center">
                            <span>🌍</span>
                            <span class="ml-3 font-semibold text-sm">Ubicaciones</span>
                        </div>
                        <svg :class="{'rotate-180': open}" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="open" x-collapse class="pl-11 pr-4 py-2 space-y-2 bg-marca-950/60 rounded-b-lg">
                        @can('modulo', ['paises'])
                        <a href="{{ route('paises.index') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Catálogo de Países</a>
                        @endcan
                        @can('modulo', ['departamentos'])
                        <a href="{{ route('departamentos.index') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Departamentos</a>
                        @endcan
                        @can('modulo', ['provincias'])
                        <a href="{{ route('provincias.index') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Provincias</a>
                        @endcan
                        @can('modulo', ['municipios'])
                        <a href="{{ route('municipios.index') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Municipios</a>
                        @endcan
                        @can('modulo', ['zonas'])
                        <a href="{{ route('zonas.index') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Zonas</a>
                        @endcan
                        <!-- Aquí está el botón nuevo -->
                        @can('modulo', ['vias-acceso'])
                        <a href="{{ route('vias-acceso.index') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Vías de Acceso</a>
                        @endcan
                    </div>
                </div>
@endcan
@can('modulo', ['empresas', 'edificios', 'contratos'])
<!-- Módulo Comercial Desplegable -->
                <div x-data="{ open: false }" class="mb-2">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 hover:bg-white/10 transition-colors rounded-lg">
                        <div class="flex items-center">
                            <span>💼</span>
                            <span class="ml-3 font-semibold text-sm">Comercial</span>
                        </div>
                        <svg :class="{'rotate-180': open}" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="open" x-collapse class="pl-11 pr-4 py-2 space-y-2 bg-marca-950/60 rounded-b-lg">
                        @can('modulo', ['empresas'])
                        <a href="{{ route('comercial.empresas') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Registro de Empresas</a>
                        @endcan
                        @can('modulo', ['edificios'])
                        <a href="{{ route('comercial.edificios') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Edificios / Proyectos</a>
                        @endcan
                        @can('modulo', ['contratos'])
                        <a href="{{ route('comercial.contratos') }}" class="block py-1 text-sm text-white font-bold hover:text-oro-300 transition">Contratos de Ventas</a>
                        @endcan
                    </div>
                </div>
@endcan
@can('modulo', ['insumos'])
                 <!-- Módulo de Almacenes Desplegable -->
                <div x-data="{ open: false }" class="mb-2">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 hover:bg-white/10 transition-colors rounded-lg">
                        <div class="flex items-center">
                            <span>📦</span>
                            <span class="ml-3 font-semibold text-sm">Almacenes</span>
                        </div>
                        <svg :class="{'rotate-180': open}" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="open" x-collapse class="pl-11 pr-4 py-2 space-y-2 bg-marca-950/60 rounded-b-lg">
                        @can('modulo', ['insumos'])
                        <a href="{{ route('almacenes.insumos') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Kardex de Inventario</a>
                        @endcan
                    </div>
                </div>
@endcan
@can('modulo', ['cargos', 'profesiones', 'personal'])
            <!-- Módulo de Recursos Humanos Desplegable -->
                <div x-data="{ open: false }" class="mb-2">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 hover:bg-white/10 transition-colors rounded-lg">
                        <div class="flex items-center">
                            <span>👥</span>
                            <span class="ml-3 font-semibold text-sm">Recursos Humanos</span>
                        </div>
                        <svg :class="{'rotate-180': open}" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="open" x-collapse class="pl-11 pr-4 py-2 space-y-2 bg-marca-950/60 rounded-b-lg">
                        @can('modulo', ['cargos'])
                        <a href="{{ route('rrhh.cargos') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Cargos Funcionales</a>
                        @endcan
                        @can('modulo', ['profesiones'])
                        <a href="{{ route('rrhh.profesiones') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Profesiones / Ocupaciones</a>
                        @endcan
                        @can('modulo', ['personal'])
                        <a href="{{ route('rrhh.personal') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Registro de Personal</a>
                        @endcan
                    </div>
                </div>
@endcan
@can('modulo', ['bancos', 'cuentas-bancarias', 'cobranzas'])
                <!-- Módulo Financiero Desplegable -->
                <div x-data="{ open: false }" class="mb-2">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 hover:bg-white/10 transition-colors rounded-lg">
                        <div class="flex items-center">
                            <span>🏦</span>
                            <span class="ml-3 font-semibold text-sm">Financiero</span>
                        </div>
                        <svg :class="{'rotate-180': open}" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="open" x-collapse class="pl-11 pr-4 py-2 space-y-2 bg-marca-950/60 rounded-b-lg">
                        @can('modulo', ['bancos'])
                        <a href="{{ route('financiero.bancos') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Bancos y Entidades</a>
                        @endcan
                        @can('modulo', ['cuentas-bancarias'])
                        <a href="{{ route('financiero.cuentas') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Cuentas Bancarias</a>
                        @endcan
                        @can('modulo', ['cobranzas'])
                        <a href="{{ route('financiero.cobranzas') }}" class="block py-1 text-sm text-white font-bold hover:text-oro-300 transition">Seguimiento de Cobranzas</a>
                        @endcan
                    </div>
                </div>
@endcan
@can('modulo', ['clasificadores-tecnicos', 'mantenimientos', 'ordenes-mantenimiento', 'emergencias', 'repuestos', 'equipos'])
                <!-- Módulo Técnico Desplegable -->
                <div x-data="{ open: true }" class="mb-2">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 hover:bg-white/10 transition-colors rounded-lg">
                        <div class="flex items-center">
                            <span>🔧</span>
                            <span class="ml-3 font-semibold text-sm">Módulo Técnico</span>
                        </div>
                        <svg :class="{'rotate-180': open}" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div x-show="open" x-collapse class="pl-11 pr-4 py-2 space-y-2 bg-marca-950/60 rounded-b-lg">
                        @can('modulo', ['clasificadores-tecnicos'])
                        <a href="{{ route('tecnico.clasificadores') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Clasificadores Técnicos</a>
                        @endcan
                        @can('modulo', ['mantenimientos', 'ordenes-mantenimiento'])
                        <a href="{{ route('tecnico.mantenimiento') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Órdenes de Mantenimiento</a>
                        @endcan
                        @can('modulo', ['emergencias'])
                        <a href="{{ route('tecnico.emergencias') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Emergencias y Reparaciones</a>
                        @endcan
                        @can('modulo', ['repuestos'])
                        <a href="{{ route('tecnico.repuestos') }}" class="block py-1 text-sm text-white font-bold hover:text-oro-300 transition">Gestión de Repuestos</a>
                        @endcan
                        @can('modulo', ['equipos'])
                        <a href="{{ route('tecnico.equipos') }}" class="block py-1 text-sm text-marca-100 hover:text-oro-300 transition">Catálogo de Equipos / Ascensores</a>
                        @endcan
                    
                    </div>
                </div>
            </nav>

           
@endcan
            <!-- Botones Inferiores (Se mantienen fijos al final) -->
            <div class="p-4 border-t border-white/20 text-sm shrink-0">
                <a href="{{ route('profile.show') }}" class="block w-full text-left px-4 py-2 text-marca-100 hover:text-oro-300 hover:bg-white/10 rounded transition mb-2">
                    ⚙️ Configuración y Seguridad
                </a>
                <form method="POST" action="{{ route('logout') }}" x-data>
                    @csrf
                    <button type="submit" class="w-full text-left px-4 py-2 text-rose-300 hover:text-rose-200 hover:bg-white/5 rounded transition">
                        🚪 Cerrar Sesión
                    </button>
                </form>
            </div>
        </aside>

        <!-- CONTENIDO PRINCIPAL -->
        <div class="flex-1 flex flex-col min-w-0 bg-marfil overflow-y-auto">
            <header class="h-16 bg-white border-b-2 border-oro-400 shadow-sm flex items-center justify-between px-8 z-10 shrink-0">
                <span class="text-marca-600 font-medium tracking-wide">Panel de Administración</span>
                @php
                    $quien = auth()->user();
                    $nombreVisible = $quien->name ?: ucfirst($quien->username);
                    $rolVisible = $quien->id === 1 ? 'Super Administrador' : ($quien->getRoleNames()->join(', ') ?: 'Sin rol asignado');
                @endphp
                <div class="flex items-center gap-3">
                    <div class="text-right leading-tight">
                        <div class="font-bold text-marca-900">{{ $nombreVisible }}</div>
                        <div class="text-xs text-gray-500">{{ $rolVisible }}</div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-marca-700 to-marca-950 ring-2 ring-oro-400 flex items-center justify-center text-oro-200 font-bold">{{ mb_strtoupper(mb_substr($nombreVisible, 0, 1)) }}</div>
                </div>
            </header>

            <main class="p-8 flex-1">
                {{ $slot }}
            </main>
        </div>

        @livewireScripts
    </body>
</html>