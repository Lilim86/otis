<div class="p-6 max-w-7xl mx-auto">
    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border-t-4 border-oro-500">
        <h2 class="text-xl font-bold mb-6 text-gray-800">{{ $equipo_id ? 'Editar Equipo' : 'Registrar Nuevo Equipo (Ascensor)' }}</h2>
        <form wire:submit="guardar" class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Código del Equipo</label>
                <input type="text" wire:model="codigo" placeholder="Ej: 36E0074" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('codigo') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción del Equipo</label>
                <input type="text" wire:model="descripcion" placeholder="Ej: ASCENSOR SOCIAL PANORAMICO" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('descripcion') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Edificio Asignado</label>
                <select wire:model="edificio_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500">
                    <option value="">NO ASIGNADO</option>
                    @foreach($edificios as $edi) <option value="{{ $edi->id }}">{{ $edi->denominacion }}</option> @endforeach
                </select> @error('edificio_id') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Industria (Origen)</label>
                <input type="text" wire:model="industria" placeholder="Ej: BRASIL" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('industria') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Marca</label>
                <input type="text" wire:model="marca" placeholder="Ej: WECO / OTIS" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('marca') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Modelo</label>
                <input type="text" wire:model="modelo" placeholder="Ej: 506-NCE" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('modelo') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Paradas</label>
                    <input type="number" wire:model="paradas" placeholder="Ej: 10" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('paradas') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select wire:model="estado" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select> @error('estado') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="md:col-span-4 flex justify-end space-x-3 mt-2">
                @if($equipo_id) <button type="button" wire:click="limpiarCampos" class="bg-gray-300 px-6 py-2 rounded font-bold">Cancelar</button> @endif
                <button type="submit" class="bg-marca-900 text-white px-8 py-2 rounded font-bold">{{ $equipo_id ? 'Actualizar' : 'Guardar' }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 border-b">
                <tr><th class="p-4 text-xs font-semibold text-gray-500 uppercase">CÓDIGO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">DESCRIPCIÓN</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">EDIFICIO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">ESTADO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase text-right">ACCIONES</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($equipos as $eq)
                <tr class="hover:bg-gray-50">
                    <td class="p-4 text-sm font-bold text-gray-800">{{ $eq->codigo }}</td>
                    <td class="p-4 text-sm font-bold text-marca-900">{{ $eq->descripcion }}</td>
                    <td class="p-4 text-sm text-gray-700">{{ $eq->edificio->denominacion ?? 'NO ASIGNADO' }}</td>
                    <td class="p-4 text-sm">@if($eq->estado) <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">Activo</span> @else <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-bold rounded-full">Inactivo</span> @endif</td>
                    <td class="p-4 text-sm text-right space-x-3"><button wire:click="editar({{ $eq->id }})" class="text-marca-600 font-semibold">Editar</button><button wire:click="eliminar({{ $eq->id }})" class="text-red-600 font-semibold">Eliminar</button></td>
                </tr>
                @empty <tr><td colspan="5" class="p-8 text-center text-gray-500">No hay equipos registrados.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
</div>