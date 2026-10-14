<div class="p-6 max-w-7xl mx-auto">
    <!-- Formulario -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border-t-4 border-oro-500">
        <h2 class="text-xl font-bold mb-6 text-gray-800">{{ $pais_id ? 'Editar País' : 'Registrar Nuevo País' }}</h2>
        
        <form wire:submit="guardar" class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del País</label>
                <input type="text" wire:model="nombre" placeholder="Ej: Bolivia" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400">
                @error('nombre') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Código ISO</label>
                <input type="text" wire:model="codigo_iso" placeholder="Ej: BO" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400"> @error('codigo_iso') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select wire:model="estado" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400">
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select> @error('estado') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            
            <div class="md:col-span-4 flex justify-end mt-2 space-x-3">
                @if($pais_id)
                    <button type="button" wire:click="limpiarCampos" class="bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded hover:bg-gray-400 transition">Cancelar</button>
                @endif
                <button type="submit" class="bg-marca-900 text-white font-bold py-2 px-8 rounded hover:bg-marca-800 transition shadow-md">
                    {{ $pais_id ? 'Actualizar País' : 'Guardar País' }}
                </button>
            </div>
        </form>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase">NOMBRE</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase">CÓDIGO ISO</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase">ESTADO</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase text-right">ACCIONES</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($paises as $pais)
                <tr class="hover:bg-gray-50 transition">
                    <td class="p-4 text-sm font-bold text-marca-900">{{ $pais->nombre }}</td>
                    <td class="p-4 text-sm text-gray-700">{{ $pais->codigo_iso ?: 'N/A' }}</td>
                    <td class="p-4 text-sm">
                        @if($pais->estado)
                            <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">Activo</span>
                        @else
                            <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-bold rounded-full">Inactivo</span>
                        @endif
                    </td>
                    <td class="p-4 text-sm text-right space-x-3">
                        <button wire:click="editar({{ $pais->id }})" class="text-marca-600 hover:text-oro-600 font-semibold">Editar</button>
                        <button wire:click="eliminar({{ $pais->id }})" class="text-red-600 hover:text-red-900 font-semibold">Eliminar</button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="p-8 text-center text-sm text-gray-500">No hay países registrados.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>