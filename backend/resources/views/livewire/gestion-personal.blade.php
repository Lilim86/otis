<div class="p-6 max-w-7xl mx-auto">
    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border-t-4 border-oro-500">
        <h2 class="text-xl font-bold mb-6 text-gray-800">{{ $personal_id ? 'Editar Personal' : 'Registrar Nuevo Personal' }}</h2>
        <form wire:submit="guardar" class="grid grid-cols-1 md:grid-cols-4 gap-6">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Cédula de Identidad (CI)</label>
                <input type="text" wire:model="ci" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('ci') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div class="md:col-span-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombres</label>
                <input type="text" wire:model="nombres" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('nombres') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Apellidos</label>
                <input type="text" wire:model="apellidos" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('apellidos') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Cargo Funcional</label>
                <select wire:model="cargo_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500">
                    <option value="">Seleccione un cargo...</option>
                    @foreach($cargos as $cargo) <option value="{{ $cargo->id }}">{{ $cargo->nombre }}</option> @endforeach
                </select> @error('cargo_id') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Profesión</label>
                <select wire:model="profesion_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500">
                    <option value="">Seleccione profesión...</option>
                    @foreach($profesiones as $profesion) <option value="{{ $profesion->id }}">{{ $profesion->nombre }}</option> @endforeach
                </select> @error('profesion_id') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select wire:model="estado" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500">
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select> @error('estado') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>

            <div class="md:col-span-4 flex justify-end mt-2 space-x-3">
                @if($personal_id) <button type="button" wire:click="limpiarCampos" class="bg-gray-300 px-6 py-2 rounded font-bold">Cancelar</button> @endif
                <button type="submit" class="bg-marca-900 text-white px-8 py-2 rounded font-bold">{{ $personal_id ? 'Actualizar' : 'Guardar' }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase">CI</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase">EMPLEADO</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase">CARGO</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase">ESTADO</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase text-right">ACCIONES</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($personal_list as $emp)
                <tr class="hover:bg-gray-50">
                    <td class="p-4 text-sm font-bold">{{ $emp->ci }}</td>
                    <td class="p-4 text-sm font-bold text-marca-900">{{ $emp->apellidos }} {{ $emp->nombres }}</td>
                    <td class="p-4 text-sm text-gray-700">{{ $emp->cargo->nombre ?? 'N/A' }}</td>
                    <td class="p-4 text-sm">@if($emp->estado) <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">Activo</span> @else <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-bold rounded-full">Inactivo</span> @endif</td>
                    <td class="p-4 text-sm text-right space-x-3"><button wire:click="editar({{ $emp->id }})" class="text-marca-600 font-semibold">Editar</button><button wire:click="eliminar({{ $emp->id }})" class="text-red-600 font-semibold">Eliminar</button></td>
                </tr>
                @empty <tr><td colspan="5" class="p-8 text-center text-gray-500">No hay personal registrado.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
</div>