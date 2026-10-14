<div class="p-6 max-w-full mx-auto">
    <div class="bg-white p-6 rounded-lg shadow-md mb-6 border-t-4 border-oro-500">
        <h2 class="text-xl font-bold text-gray-800">Reportes combinados</h2>
        <p class="text-sm text-gray-500 mb-4">Cada reporte une el módulo con las tablas relacionadas (cliente, edificio, almacén, banco…) y su ubicación completa. Ves solo los de tu rol.</p>

        @if(empty($disponibles))
            <p class="text-gray-500">Tu rol no tiene reportes disponibles.</p>
        @else
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Reporte</label>
                <select wire:model.live="clave" class="w-full border-gray-300 rounded-md shadow-sm">
                    <option value="">— Elige un reporte —</option>
                    @foreach($disponibles as $area => $lista)
                        <optgroup label="{{ $area }}">
                            @foreach($lista as $k => $titulo) <option value="{{ $k }}">{{ $titulo }}</option> @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Desde</label><input type="date" wire:model.live="desde" class="w-full border-gray-300 rounded-md"></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1">Hasta</label><input type="date" wire:model.live="hasta" class="w-full border-gray-300 rounded-md"></div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Regional / departamento</label>
                <select wire:model.live="departamento_id" class="w-full border-gray-300 rounded-md">
                    <option value="">Todos</option>
                    @foreach($departamentos as $d) <option value="{{ $d->id }}">{{ $d->nombre }}</option> @endforeach
                </select>
            </div>
            <div class="md:col-span-3"><label class="block text-sm font-medium text-gray-700 mb-1">Buscar en el reporte</label><input type="text" wire:model.live.debounce.400ms="q" class="w-full border-gray-300 rounded-md" placeholder="Cliente, edificio, código…"></div>
            @if($reporte)
            <div class="md:col-span-2 flex items-end justify-end gap-3">
                <a href="{{ route('reportes.descargar', ['clave' => $clave, 'formato' => 'csv'] + $consulta) }}" class="px-5 py-2 rounded font-bold border border-marca-900 text-marca-900 hover:bg-gray-50">Excel (CSV)</a>
                <a href="{{ route('reportes.descargar', ['clave' => $clave, 'formato' => 'pdf'] + $consulta) }}" target="_blank" class="px-5 py-2 rounded font-bold bg-marca-900 text-white hover:bg-marca-800">Imprimir PDF</a>
            </div>
            @endif
        </div>
        @endif
    </div>

    @if($reporte)
    <div class="bg-white rounded-lg shadow-md overflow-x-auto">
        <div class="px-4 py-3 border-b text-sm text-gray-600">{{ $reporte['titulo'] }} — {{ count($reporte['filas']) }} registro(s)@if(count($reporte['filas']) > $vistaPrevia) (vista previa de {{ $vistaPrevia }}; el PDF y el CSV traen todos)@endif</div>
        <table class="w-full text-left border-collapse text-xs">
            <thead class="bg-gray-50 border-b"><tr>@foreach($reporte['columnas'] as $etiqueta) <th class="p-3 font-semibold text-gray-500 uppercase whitespace-nowrap">{{ $etiqueta }}</th> @endforeach</tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse(array_slice($reporte['filas'], 0, $vistaPrevia) as $fila)
                    <tr class="hover:bg-gray-50">@foreach(array_keys($reporte['columnas']) as $c) <td class="p-3 text-gray-700">{{ $fila[$c] ?? '' }}</td> @endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($reporte['columnas']) }}" class="p-8 text-center text-gray-500">No hay datos para este reporte.</td></tr>
                @endforelse
                @if($reporte['sumas'])
                    <tr class="bg-gray-50 font-bold text-marca-900">@foreach(array_keys($reporte['columnas']) as $i => $c) <td class="p-3">{{ $i === 0 ? 'TOTALES' : (isset($reporte['sumas'][$c]) ? number_format($reporte['sumas'][$c], 2) : '') }}</td> @endforeach</tr>
                @endif
            </tbody>
        </table>
    </div>
    @endif
</div>
