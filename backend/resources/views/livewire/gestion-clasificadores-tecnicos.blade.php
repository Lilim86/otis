<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-t-4 border-oro-500">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Clasificadores del Área Técnica</h2>

    @if (session()->has('mensaje'))
        <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-md">{{ session('mensaje') }}</div>
    @endif

    <form wire:submit="guardar" class="mb-8 flex items-end gap-4">
        <div class="w-1/3">
            <label class="block text-sm font-medium text-gray-700">Nombre del Clasificador</label>
            <input type="text" wire:model="nombre" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-marca-900" placeholder="Ej: Falla Mecánica" required> @error('nombre') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
        </div>
        <div class="flex-1">
            <label class="block text-sm font-medium text-gray-700">Descripción</label>
            <input type="text" wire:model="descripcion" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-marca-900" placeholder="Ej: Problemas en poleas o cables de tracción"> @error('descripcion') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
        </div>
        <button type="submit" class="bg-marca-900 text-white px-6 py-2 rounded-md hover:bg-marca-800 transition">Guardar</button>
    </form>

    <table class="min-w-full divide-y divide-gray-200 border">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Clasificador</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descripción</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @foreach($clasificadores as $item)
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $item->id }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-marca-900">{{ $item->nombre }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $item->descripcion }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>