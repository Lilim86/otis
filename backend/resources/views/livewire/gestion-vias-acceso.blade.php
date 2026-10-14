<div class="p-6 max-w-7xl mx-auto">
    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border-t-4 border-oro-500">
        <h2 class="text-xl font-bold mb-6 text-gray-800">{{ $via_id ? 'Editar Vía de Acceso' : 'Registrar Nueva Vía de Acceso' }}</h2>
        
        <form wire:submit="guardar" class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Zona / Barrio</label>
                <select wire:model="zona_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400">
                    <option value="">Seleccione una zona...</option>
                    @foreach($zonas as $zona)
                        <option value="{{ $zona->id }}">{{ $zona->nombre }}</option>
                    @endforeach
                </select>
                @error('zona_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de la Vía (Calle, Av.)</label>
                <input type="text" wire:model="nombre" placeholder="Ej: Av. 6 de Agosto" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400">
                @error('nombre') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select wire:model="estado" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400">
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select> @error('estado') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            
            <div class="md:col-span-4 flex justify-end mt-2 space-x-3">
                @if($via_id)
                    <button type="button" wire:click="limpiarCampos" class="bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded hover:bg-gray-400 transition">Cancelar</button>
                @endif
                <button type="submit" class="bg-marca-900 text-white font-bold py-2 px-8 rounded hover:bg-marca-800 transition shadow-md">
                    {{ $via_id ? 'Actualizar' : 'Guardar' }}
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase">VÍA DE ACCESO</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase">ZONA / BARRIO</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase">ESTADO</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase text-right">ACCIONES</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($vias as $via)
                <tr class="hover:bg-gray-50 transition">
                    <td class="p-4 text-sm font-bold text-marca-900">{{ $via->nombre }}</td>
                    <td class="p-4 text-sm text-gray-700">{{ $via->zona->nombre ?? 'Sin Zona' }}</td>
                    <td class="p-4 text-sm">
                        @if($via->estado)
                            <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">Activo</span>
                        @else
                            <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-bold rounded-full">Inactivo</span>
                        @endif
                    </td>
                    <td class="p-4 text-sm text-right space-x-3">
                        <button wire:click="editar({{ $via->id }})" class="text-marca-600 hover:text-oro-600 font-semibold">Editar</button>
                        <button wire:click="eliminar({{ $via->id }})" class="text-red-600 hover:text-red-900 font-semibold">Eliminar</button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="p-8 text-center text-sm text-gray-500">No hay vías de acceso registradas.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>