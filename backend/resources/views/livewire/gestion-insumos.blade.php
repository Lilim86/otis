<div class="p-6 max-w-7xl mx-auto">
    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border-t-4 border-oro-500">
        <div class="flex justify-end mb-4">
        <a href="{{ route('reportes.kardex') }}" target="_blank" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded shadow flex items-center">
            <span class="mr-2">📄</span> Imprimir Reporte PDF
        </a>
    </div>
        <h2 class="text-xl font-bold mb-6 text-gray-800">{{ $insumo_id ? 'Editar Insumo/Herramienta' : 'Registrar Nuevo Insumo' }}</h2>
        <form wire:submit="guardar" class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Código del Bien</label>
                <input type="text" wire:model="codigo" placeholder="Ej: HERR-0493" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('codigo') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción del Bien</label>
                <input type="text" wire:model="descripcion" placeholder="Ej: ACEITERA DE 500 ML" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('descripcion') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Bien</label>
                <select wire:model="tipo" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500">
                    <option value="HERRAMIENTA">HERRAMIENTA</option>
                    <option value="REPUESTO">REPUESTO</option>
                    <option value="MATERIAL">MATERIAL (Insumo)</option>
                </select> @error('tipo') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Stock Físico Actual</label>
                <input type="number" step="0.01" wire:model="stock_actual" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('stock_actual') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select wire:model="estado" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500">
                    <option value="1">Activo en Almacén</option>
                    <option value="0">Descontinuado / Inactivo</option>
                </select> @error('estado') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>

            <div class="md:col-span-2 flex justify-end items-end space-x-3 mt-2">
                @if($insumo_id) <button type="button" wire:click="limpiarCampos" class="bg-gray-300 px-6 py-2 rounded font-bold">Cancelar</button> @endif
                <button type="submit" class="bg-marca-900 text-white px-8 py-2 rounded font-bold">{{ $insumo_id ? 'Actualizar Kardex' : 'Guardar en Kardex' }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 border-b">
                <tr><th class="p-4 text-xs font-semibold text-gray-500 uppercase">CÓDIGO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">DESCRIPCIÓN</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">TIPO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">SALDO FÍSICO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">ESTADO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase text-right">ACCIONES</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($insumos as $in)
                <tr class="hover:bg-gray-50">
                    <td class="p-4 text-sm font-bold text-gray-800">{{ $in->codigo }}</td>
                    <td class="p-4 text-sm font-bold text-marca-900">{{ $in->descripcion }}</td>
                    <td class="p-4 text-sm text-gray-700">{{ $in->tipo }}</td>
                    <td class="p-4 text-sm font-bold {{ $in->stock_actual <= 5 ? 'text-red-600' : 'text-green-700' }}">{{ $in->stock_actual }}</td>
                    <td class="p-4 text-sm">@if($in->estado) <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">Activo</span> @else <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-bold rounded-full">Inactivo</span> @endif</td>
                    <td class="p-4 text-sm text-right space-x-3"><button wire:click="editar({{ $in->id }})" class="text-marca-600 font-semibold">Editar</button><button wire:click="eliminar({{ $in->id }})" class="text-red-600 font-semibold">Eliminar</button></td>
                </tr>
                @empty <tr><td colspan="6" class="p-8 text-center text-gray-500">No hay bienes registrados en el Kardex.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
</div>