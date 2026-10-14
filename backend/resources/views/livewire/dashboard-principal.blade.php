<div class="p-6 max-w-7xl mx-auto">
    <div class="flex flex-col md:flex-row justify-between items-center mb-8">
        <h1 class="text-3xl font-black text-marca-900">Panel de Control LILIM</h1>
        <span class="bg-oro-100 text-marca-900 text-sm font-bold px-5 py-2 rounded-full mt-4 md:mt-0 shadow-sm border border-oro-300">
            👋 Bienvenido/a, {{ auth()->user()->name }}
        </span>
    </div>

    <!-- Consumo de API Externa -->
    <div class="bg-gradient-to-r from-marca-950 via-marca-900 to-marca-700 rounded-2xl p-8 text-white shadow-xl mb-10 flex justify-between items-center transform hover:scale-[1.02] transition-transform duration-300">
        <div>
            <p class="text-sm font-semibold text-oro-200 mb-2 uppercase tracking-wider">Mercado Cambiario (Consumo API Externa)</p>
            <h2 class="text-4xl md:text-5xl font-black mb-2">1 USD = Bs. {{ is_numeric($precioDolar) ? number_format((float)$precioDolar, 2) : $precioDolar }}</h2>
            <p class="text-xs text-oro-200 opacity-80 flex items-center">
                <span class="w-2 h-2 rounded-full bg-green-400 mr-2 animate-pulse"></span>
                Datos actualizados en tiempo real vía ExchangeRate-API
            </p>
        </div>
        <div class="text-7xl opacity-90 hidden md:block">🌎</div>
    </div>

    <!-- Tarjetas de Estadísticas Internas -->
    <h3 class="text-lg font-bold text-gray-700 mb-4">Resumen Operativo</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <div class="bg-white p-6 rounded-2xl shadow-md border-l-4 border-marca-600 flex flex-col justify-center">
            <p class="text-xs text-gray-500 font-bold mb-1 uppercase">Edificios Registrados</p>
            <p class="text-3xl font-black text-gray-800">{{ $totalEdificios }}</p>
        </div>
        
        <div class="bg-white p-6 rounded-2xl shadow-md border-l-4 border-oro-500 flex flex-col justify-center">
            <p class="text-xs text-gray-500 font-bold mb-1 uppercase">Equipos / Ascensores</p>
            <p class="text-3xl font-black text-gray-800">{{ $totalEquipos }}</p>
        </div>
        
        <div class="bg-white p-6 rounded-2xl shadow-md border-l-4 border-emerald-600 flex flex-col justify-center">
            <p class="text-xs text-gray-500 font-bold mb-1 uppercase">Contratos Emitidos</p>
            <p class="text-3xl font-black text-gray-800">{{ $totalContratos }}</p>
        </div>
        
        <div class="bg-white p-6 rounded-2xl shadow-md border-l-4 border-rose-800 flex flex-col justify-center">
            <p class="text-xs text-gray-500 font-bold mb-1 uppercase">Valor Total Contratos</p>
            <p class="text-2xl font-black text-gray-800">Bs. {{ number_format($recaudacion, 2) }}</p>
        </div>

    </div>
</div>