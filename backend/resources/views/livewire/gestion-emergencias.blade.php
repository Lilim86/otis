<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-t-4 border-oro-500">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Gestión de Emergencias y Reparaciones</h2>

    @if (session()->has('mensaje'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-md">{{ session('mensaje') }}</div>
    @endif

    <form wire:submit="guardar" class="mb-8 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Seleccionar Ascensor Oficial (Catálogo)</label>
            <select wire:model="ascensor_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-marca-900" required>
                <option value="">-- Elija un ascensor registrado --</option>
                @foreach($ascensores as $asc)
                    <option value="{{ $asc->id }}">{{ $asc->codigo }} - {{ $asc->cliente_edificio }} ({{ $asc->zona->nombre }})</option>
                @endforeach
            </select> @error('ascensor_id') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Nivel de Gravedad</label>
            <select wire:model="gravedad" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-marca-900" required>
                <option value="Leve">Leve</option>
                <option value="Moderada">Moderada</option>
                <option value="Crítica">Crítica (Atrapamiento)</option>
            </select> @error('gravedad') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
        </div>
        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Descripción del Fallo</label>
            <input type="text" wire:model="descripcion_fallo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-marca-900" placeholder="Ej: Falla en puertas o botonera" required> @error('descripcion_fallo') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
        </div>
        <div class="flex justify-end">
            <button type="submit" class="bg-marca-900 text-white px-6 py-2 rounded-md hover:bg-marca-800 transition">Registrar Emergencia</button>
        </div>
    </form>

    <table class="min-w-full divide-y divide-gray-200 border">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ascensor / Edificio</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zona</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gravedad</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @foreach($emergencias as $emer)
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <span class="font-bold text-marca-900">{{ optional($emer->ascensor)->codigo }}</span><br>
                    <span class="text-xs text-gray-500">{{ optional($emer->ascensor)->cliente_edificio }}</span>
                </td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ optional(optional($emer->ascensor)->zona)->nombre }}</td>                <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <span class="px-2 py-1 rounded text-xs font-semibold {{ $emer->gravedad == 'Crítica' ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800' }}">
                        {{ $emer->gravedad }}
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $emer->descripcion_fallo }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <span class="px-2 py-1 rounded text-xs font-semibold bg-yellow-100 text-yellow-800">
                        {{ $emer->situacion }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>