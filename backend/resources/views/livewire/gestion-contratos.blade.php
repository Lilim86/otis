<div class="p-6 max-w-7xl mx-auto">
    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border-t-4 border-oro-500">
        <h2 class="text-xl font-bold mb-6 text-gray-800">{{ $contrato_id ? 'Editar Contrato' : 'Registrar Nuevo Contrato de Venta' }}</h2>
        <form wire:submit="guardar" class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Empresa / Cliente</label>
                <select wire:model="empresa_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500">
                    <option value="">Seleccione el cliente...</option>
                    @foreach($empresas as $emp) <option value="{{ $emp->id }}">{{ $emp->denominacion }}</option> @endforeach
                </select> @error('empresa_id') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Edificio / Ubicación</label>
                <select wire:model="edificio_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500">
                    <option value="">Seleccione el edificio...</option>
                    @foreach($edificios as $edi) <option value="{{ $edi->id }}">{{ $edi->denominacion }}</option> @endforeach
                </select> @error('edificio_id') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de Inicio</label>
                <input type="date" wire:model="fecha_inicio" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('fecha_inicio') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de Fin</label>
                <input type="date" wire:model="fecha_fin" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('fecha_fin') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Monto Total (Bs.)</label>
                <input type="number" step="0.01" wire:model="monto_total" placeholder="Ej: 25000.00" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('monto_total') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                <select wire:model="estado" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500">
                    <option value="1">Vigente</option>
                    <option value="0">Vencido / Anulado</option>
                </select> @error('estado') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>

            <div class="md:col-span-4 flex justify-end space-x-3 mt-2">
                @if($contrato_id) <button type="button" wire:click="limpiarCampos" class="bg-gray-300 px-6 py-2 rounded font-bold">Cancelar</button> @endif
                <button type="submit" class="bg-marca-900 text-white px-8 py-2 rounded font-bold">{{ $contrato_id ? 'Actualizar' : 'Guardar' }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 border-b">
                <tr><th class="p-4 text-xs font-semibold text-gray-500 uppercase">CLIENTE</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">EDIFICIO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">PERIODO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">MONTO (Bs)</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">ESTADO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase text-right">ACCIONES</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($contratos as $c)
                <tr class="hover:bg-gray-50">
                    <td class="p-4 text-sm font-bold text-marca-900">{{ $c->empresa->denominacion ?? 'N/A' }}</td>
                    <td class="p-4 text-sm text-gray-700">{{ $c->edificio->denominacion ?? 'N/A' }}</td>
                    <td class="p-4 text-sm text-gray-600">{{ \Carbon\Carbon::parse($c->fecha_inicio)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($c->fecha_fin)->format('d/m/Y') }}</td>
                    <td class="p-4 text-sm font-bold text-green-700">{{ number_format($c->monto_total, 2) }}</td>
                    <td class="p-4 text-sm">@if($c->estado) <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">Vigente</span> @else <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-bold rounded-full">Vencido</span> @endif</td>
                    <td class="p-4 text-sm text-right space-x-3"><button wire:click="editar({{ $c->id }})" class="text-marca-600 font-semibold">Editar</button><button wire:click="eliminar({{ $c->id }})" class="text-red-600 font-semibold">Eliminar</button></td>
                </tr>
                @empty <tr><td colspan="6" class="p-8 text-center text-gray-500">No hay contratos registrados.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
</div>