<div class="p-6 max-w-7xl mx-auto">
    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border-t-4 border-oro-500">
        <h2 class="text-xl font-bold mb-6 text-gray-800">{{ $cobranza_id ? 'Editar Cobranza' : 'Registrar Cuota / Cobranza' }}</h2>
        <form wire:submit="guardar" class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Contrato Relacionado</label>
                <select wire:model="contrato_id" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500">
                    <option value="">Seleccione el contrato...</option>
                    @foreach($contratos as $con) <option value="{{ $con->id }}">{{ $con->empresa->denominacion ?? 'Sin Empresa' }} - (Bs. {{ $con->monto_total }})</option> @endforeach
                </select> @error('contrato_id') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Concepto</label>
                <input type="text" wire:model="concepto" placeholder="Ej: 1RA CUOTA MANTENIMIENTO" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('concepto') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Monto a Cobrar (Bs.)</label>
                <input type="number" step="0.01" wire:model="monto_facturado" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('monto_facturado') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de Cobro</label>
                <input type="date" wire:model="fecha_cobro" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('fecha_cobro') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nro. Factura (Opcional)</label>
                <input type="text" wire:model="nro_factura" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500"> @error('nro_factura') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estado de la Cuota</label>
                <select wire:model="estado" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500">
                    <option value="0">Pendiente de Pago</option>
                    <option value="1">Pagado / Completado</option>
                </select> @error('estado') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>

            <div class="md:col-span-4 flex justify-end space-x-3 mt-2">
                @if($cobranza_id) <button type="button" wire:click="limpiarCampos" class="bg-gray-300 px-6 py-2 rounded font-bold">Cancelar</button> @endif
                <button type="submit" class="bg-marca-900 text-white px-8 py-2 rounded font-bold">{{ $cobranza_id ? 'Actualizar' : 'Guardar' }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 border-b">
                <tr><th class="p-4 text-xs font-semibold text-gray-500 uppercase">CLIENTE</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">CONCEPTO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">FECHA</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">MONTO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">ESTADO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase text-right">ACCIONES</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($cobranzas as $cob)
                <tr class="hover:bg-gray-50">
                    <td class="p-4 text-sm font-bold text-marca-900">{{ $cob->contrato->empresa->denominacion ?? 'N/A' }}</td>
                    <td class="p-4 text-sm text-gray-700">{{ $cob->concepto }}</td>
                    <td class="p-4 text-sm text-gray-600">{{ \Carbon\Carbon::parse($cob->fecha_cobro)->format('d/m/Y') }}</td>
                    <td class="p-4 text-sm font-bold text-gray-800">Bs. {{ number_format($cob->monto_facturado, 2) }}</td>
                    <td class="p-4 text-sm">@if($cob->estado) <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-bold rounded-full">Pagado</span> @else <span class="px-3 py-1 bg-yellow-100 text-yellow-800 text-xs font-bold rounded-full">Pendiente</span> @endif</td>
                    <td class="p-4 text-sm text-right space-x-3"><button wire:click="editar({{ $cob->id }})" class="text-marca-600 font-semibold">Editar</button><button wire:click="eliminar({{ $cob->id }})" class="text-red-600 font-semibold">Eliminar</button></td>
                </tr>
                @empty <tr><td colspan="6" class="p-8 text-center text-gray-500">No hay cobranzas registradas.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
</div>