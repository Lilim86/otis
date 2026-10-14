<div class="p-6 max-w-7xl mx-auto">
    <!-- Panel del Formulario -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border-t-4 border-oro-500">
        <h2 class="text-xl font-bold mb-6 text-gray-800">Registrar / Editar Repuesto</h2>
        
        <form wire:submit="guardar" class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Código Oficial</label>
                <input type="text" wire:model="codigo_parte" placeholder="Ej: REP-OTIS-001" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400">
                @error('codigo_parte') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre de la Parte</label>
                <input type="text" wire:model="nombre" placeholder="Ej: Cable de tracción" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400">
                @error('nombre') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Stock</label>
                <input type="number" wire:model="stock" placeholder="Cantidad" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400">
                @error('stock') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Precio (BOB)</label>
                <input type="number" step="0.01" wire:model="precio" placeholder="Precio unitario" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400">
                @error('precio') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Ubicación en Almacén</label>
                <input type="text" wire:model="ubicacion_almacen" placeholder="Ej: Almacén Central La Paz - Estante 3" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500 focus:ring-oro-400">
                @error('ubicacion_almacen') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            
            <div class="md:col-span-3 flex justify-end mt-2">
                <button type="button" wire:click="limpiarCampos" class="mr-4 bg-gray-200 text-gray-700 font-bold py-2 px-6 rounded hover:bg-gray-300 transition">Cancelar</button>
                <button type="submit" class="bg-marca-900 text-white font-bold py-2 px-8 rounded hover:bg-marca-800 transition shadow-md">Guardar Datos</button>
            </div>
        </form>
    </div>

    <!-- Tabla de Inventario -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">CÓDIGO</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">NOMBRE</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-center">STOCK</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">PRECIO</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">UBICACIÓN</th>
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">ACCIONES</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($repuestos as $repuesto)
                <tr class="hover:bg-gray-50 transition">
                    <td class="p-4 text-sm font-medium text-gray-900">{{ $repuesto->codigo_parte }}</td>
                    <td class="p-4 text-sm text-gray-700">{{ $repuesto->nombre }}</td>
                    <td class="p-4 text-sm text-center">
                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-bold rounded-full {{ $repuesto->stock > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $repuesto->stock }}
                        </span>
                    </td>
                    <td class="p-4 text-sm text-gray-700">Bs. {{ number_format($repuesto->precio, 2) }}</td>
                    <td class="p-4 text-sm text-gray-700">{{ $repuesto->ubicacion_almacen }}</td>
                    <td class="p-4 text-sm text-right space-x-4">
                        <button wire:click="editar({{ $repuesto->id }})" class="text-marca-700 hover:text-oro-600 font-semibold">Editar</button>
                        <button wire:click="eliminar({{ $repuesto->id }})" class="text-red-600 hover:text-red-900 font-semibold">Eliminar</button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="p-8 text-center text-sm text-gray-500">No hay repuestos registrados en el sistema OTIS.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>