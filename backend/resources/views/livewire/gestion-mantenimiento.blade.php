<div class="p-6 max-w-7xl mx-auto">
    <!-- Formulario -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border-t-4 border-oro-500">
        <h2 class="text-xl font-bold mb-6 text-gray-800">Programar Mantenimiento</h2>
        
        <form wire:submit="guardar" class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Código de Orden</label>
                <input type="text" wire:model="codigo_orden" placeholder="Ej: ORD-001" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400"> @error('codigo_orden') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ascensor a Revisar</label>
                <input type="text" wire:model="equipo_ascensor" placeholder="Ej: Torre A - Ascensor 1" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400"> @error('equipo_ascensor') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha Programada</label>
                <input type="date" wire:model="fecha_programada" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400"> @error('fecha_programada') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select wire:model="situacion" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400">
                    <option value="Pendiente">Pendiente</option>
                    <option value="En Proceso">En Proceso</option>
                </select> @error('situacion') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            
            <!-- Conexión con Repuestos -->
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Repuesto Requerido (Opcional)</label>
                <select wire:model="repuesto_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400">
                    <option value="">Ninguno / Solo revisión</option>
                    @foreach($lista_repuestos as $repuesto)
                        <option value="{{ $repuesto->id }}">{{ $repuesto->nombre }} (Stock: {{ $repuesto->stock }})</option>
                    @endforeach
                </select> @error('repuesto_id') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Cantidad a Utilizar</label>
                <input type="number" wire:model="cantidad_utilizada" min="1" placeholder="Ej: 2" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400"> @error('cantidad_utilizada') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>

            <div class="md:col-span-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción del Trabajo</label>
                <textarea wire:model="descripcion_trabajo" rows="2" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400" placeholder="Detalles técnicos..."></textarea> @error('descripcion_trabajo') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            
            <div class="md:col-span-4 flex justify-end mt-2">
                <button type="submit" class="bg-marca-900 text-white font-bold py-2 px-8 rounded hover:bg-marca-800 transition shadow-md">Guardar Orden</button>
            </div>
        </form>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase">CÓDIGO</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase">EQUIPO</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase">FECHA</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase">ESTADO</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase">REPUESTO ASIGNADO</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase text-right">ACCIONES</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($ordenes as $orden)
                <tr class="hover:bg-gray-50 transition">
                    <td class="p-4 text-sm font-bold text-marca-900">{{ $orden->codigo_orden }}</td>
                    <td class="p-4 text-sm text-gray-700">{{ $orden->equipo_ascensor }}</td>
                    <td class="p-4 text-sm text-gray-700">{{ \Carbon\Carbon::parse($orden->fecha_programada)->format('d/m/Y') }}</td>
                    <td class="p-4 text-sm">
                        @if($orden->situacion == 'Pendiente')
                            <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-xs font-bold rounded-full">Pendiente</span>
                        @elseif($orden->situacion == 'En Proceso')
                            <span class="px-3 py-1 bg-oro-100 text-marca-800 text-xs font-bold rounded-full">En Proceso</span>
                        @else
                            <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">Finalizado</span>
                        @endif
                    </td>
                    <td class="p-4 text-sm text-gray-700">
                        @if($orden->repuesto)
                            {{ $orden->repuesto->nombre }} (x{{ $orden->cantidad_utilizada }})
                        @else
                            <span class="text-gray-400">N/A</span>
                        @endif
                    </td>
                    <td class="p-4 text-sm text-right space-x-3">
                        @if($orden->situacion !== 'Finalizado')
                            <button wire:click="finalizarOrden({{ $orden->id }})" class="text-green-600 hover:text-green-900 font-bold bg-green-50 px-2 py-1 rounded border border-green-200">Completar y Descontar Pieza</button>
                        @endif
                        <button wire:click="eliminar({{ $orden->id }})" class="text-red-600 hover:text-red-900 font-semibold">Eliminar</button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="p-8 text-center text-sm text-gray-500">No hay órdenes de mantenimiento programadas.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>