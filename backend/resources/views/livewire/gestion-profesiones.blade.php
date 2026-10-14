<div class="p-6 max-w-7xl mx-auto">
    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border-t-4 border-oro-500">
        <h2 class="text-xl font-bold mb-6 text-gray-800">{{ $profesion_id ? 'Editar Profesión' : 'Registrar Profesión / Ocupación' }}</h2>
        <form wire:submit="guardar" class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de la Profesión</label>
                <input type="text" wire:model="nombre" placeholder="Ej: Ingeniero Electromecánico" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('nombre') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select wire:model="estado" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500">
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select> @error('estado') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div class="flex items-end space-x-3">
                @if($profesion_id) <button type="button" wire:click="limpiarCampos" class="bg-gray-300 px-4 py-2 rounded font-bold">Cancelar</button> @endif
                <button type="submit" class="bg-marca-900 text-white px-6 py-2 rounded font-bold">{{ $profesion_id ? 'Actualizar' : 'Guardar' }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 border-b">
                <tr><th class="p-4 text-xs font-semibold text-gray-500 uppercase">PROFESIÓN / OCUPACIÓN</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">ESTADO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase text-right">ACCIONES</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($profesiones as $profesion)
                <tr class="hover:bg-gray-50">
                    <td class="p-4 text-sm font-bold text-marca-900">{{ $profesion->nombre }}</td>
                    <td class="p-4 text-sm">@if($profesion->estado) <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">Activo</span> @else <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-bold rounded-full">Inactivo</span> @endif</td>
                    <td class="p-4 text-sm text-right space-x-3"><button wire:click="editar({{ $profesion->id }})" class="text-marca-600 font-semibold">Editar</button><button wire:click="eliminar({{ $profesion->id }})" class="text-red-600 font-semibold">Eliminar</button></td>
                </tr>
                @empty <tr><td colspan="3" class="p-8 text-center text-gray-500">No hay profesiones registradas.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
</div>