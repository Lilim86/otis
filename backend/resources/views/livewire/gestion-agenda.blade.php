<div class="p-6 max-w-7xl mx-auto">
    @if (session()->has('mensaje'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 shadow-sm font-bold" role="alert">{{ session('mensaje') }}</div>
    @endif

    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border-t-4 border-oro-500">
        <h2 class="text-xl font-bold text-gray-800">{{ $cita_id ? 'Modificar Cita' : 'Registrar Cita en la Agenda' }}</h2>
        <p class="text-sm text-gray-500 mb-6">Agenda de: <span class="font-semibold text-marca-900">{{ $gerente }}</span></p>

        <form wire:submit="guardar" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Asunto</label>
                <input type="text" wire:model="titulo" placeholder="Ej: Renovación de contrato con el edificio" class="w-full border-gray-300 rounded-md shadow-sm">
                @error('titulo') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                <select wire:model="tipo" class="w-full border-gray-300 rounded-md shadow-sm">
                    @foreach($tipos as $clave => $etiqueta) <option value="{{ $clave }}">{{ $etiqueta }}</option> @endforeach
                </select> @error('tipo') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Situación</label>
                <select wire:model="situacion" class="w-full border-gray-300 rounded-md shadow-sm">
                    @foreach($situaciones as $clave => $etiqueta) <option value="{{ $clave }}">{{ $etiqueta }}</option> @endforeach
                </select> @error('situacion') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Inicio</label>
                <input type="datetime-local" wire:model="fecha_inicio" class="w-full border-gray-300 rounded-md shadow-sm">
                @error('fecha_inicio') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fin</label>
                <input type="datetime-local" wire:model="fecha_fin" class="w-full border-gray-300 rounded-md shadow-sm">
                @error('fecha_fin') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Con quién</label>
                <input type="text" wire:model="con_quien" placeholder="Nombre del visitante o contacto" class="w-full border-gray-300 rounded-md shadow-sm"> @error('con_quien') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
                <input type="text" wire:model="telefono" class="w-full border-gray-300 rounded-md shadow-sm"> @error('telefono') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Empresa</label>
                <select wire:model="empresa_id" class="w-full border-gray-300 rounded-md shadow-sm">
                    <option value="">— Ninguna —</option>
                    @foreach($empresas as $e) <option value="{{ $e->id }}">{{ $e->denominacion }}</option> @endforeach
                </select> @error('empresa_id') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Edificio</label>
                <select wire:model="edificio_id" class="w-full border-gray-300 rounded-md shadow-sm">
                    <option value="">— Ninguno —</option>
                    @foreach($edificios as $e) <option value="{{ $e->id }}">{{ $e->denominacion }}</option> @endforeach
                </select> @error('edificio_id') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Lugar</label>
                <input type="text" wire:model="lugar" placeholder="Oficina, obra, videollamada…" class="w-full border-gray-300 rounded-md shadow-sm"> @error('lugar') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div class="md:col-span-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo / notas</label>
                <textarea wire:model="motivo" rows="2" class="w-full border-gray-300 rounded-md shadow-sm"></textarea> @error('motivo') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror
            </div>
            <div class="md:col-span-4 flex justify-end gap-3">
                @if($cita_id) <button type="button" wire:click="limpiarCampos" class="px-6 py-2 rounded font-bold border border-gray-300 text-gray-700">Cancelar</button> @endif
                <button type="submit" class="bg-marca-900 text-white px-8 py-2 rounded font-bold">{{ $cita_id ? 'Guardar cambios' : 'Registrar cita' }}</button>
            </div>
        </form>
    </div>

    <div class="bg-white p-4 rounded-lg shadow-md mb-4 grid grid-cols-1 md:grid-cols-4 gap-4">
        <div><label class="block text-xs text-gray-500">Desde</label><input type="date" wire:model.live="desde" class="w-full border-gray-300 rounded-md"> @error('desde') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror</div>
        <div><label class="block text-xs text-gray-500">Hasta</label><input type="date" wire:model.live="hasta" class="w-full border-gray-300 rounded-md"> @error('hasta') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror</div>
        <div class="md:col-span-2"><label class="block text-xs text-gray-500">Buscar</label><input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Asunto o visitante" class="w-full border-gray-300 rounded-md"> @error('buscar') <span class="block mt-1 text-xs text-rose-600">{{ $message }}</span> @enderror</div>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 border-b">
                <tr>
                    @foreach(['FECHA Y HORA', 'ASUNTO', 'CON QUIEN', 'EMPRESA / EDIFICIO', 'SITUACIÓN'] as $th)
                        <th class="p-4 text-xs font-semibold text-gray-500 uppercase">{{ $th }}</th>
                    @endforeach
                    <th class="p-4 text-xs font-semibold text-gray-500 uppercase text-right">ACCIONES</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($citas as $c)
                <tr class="hover:bg-gray-50">
                    <td class="p-4 text-sm text-gray-700">{{ $c->fecha_inicio->format('d/m/Y H:i') }} – {{ $c->fecha_fin->format('H:i') }}</td>
                    <td class="p-4 text-sm font-bold text-marca-900">{{ $c->titulo }}<div class="text-xs font-normal text-gray-500">{{ $tipos[$c->tipo] ?? $c->tipo }}{{ $c->lugar ? ' · '.$c->lugar : '' }}</div></td>
                    <td class="p-4 text-sm text-gray-700">{{ $c->con_quien ?: '—' }}<div class="text-xs text-gray-500">{{ $c->telefono }}</div></td>
                    <td class="p-4 text-sm text-gray-700">{{ $c->empresa?->denominacion ?: '—' }}<div class="text-xs text-gray-500">{{ $c->edificio?->denominacion }}</div></td>
                    <td class="p-4 text-sm">
                        <span class="px-3 py-1 text-xs font-bold rounded-full {{ ['PROGRAMADA' => 'bg-oro-100 text-marca-800', 'CONFIRMADA' => 'bg-green-100 text-green-800', 'ATENDIDA' => 'bg-gray-200 text-gray-700', 'CANCELADA' => 'bg-red-100 text-red-800'][$c->situacion] ?? '' }}">{{ $situaciones[$c->situacion] ?? $c->situacion }}</span>
                    </td>
                    <td class="p-4 text-sm text-right space-x-3">
                        @if($c->situacion === 'PROGRAMADA') <button wire:click="cambiarSituacion({{ $c->id }}, 'CONFIRMADA')" class="text-green-700 font-semibold">Confirmar</button> @endif
                        @if(in_array($c->situacion, ['PROGRAMADA', 'CONFIRMADA'])) <button wire:click="cambiarSituacion({{ $c->id }}, 'ATENDIDA')" class="text-gray-700 font-semibold">Atendida</button> <button wire:click="cambiarSituacion({{ $c->id }}, 'CANCELADA')" wire:confirm="¿Cancelar esta cita?" class="text-amber-600 font-semibold">Cancelar</button> @endif
                        <button wire:click="editar({{ $c->id }})" class="text-marca-600 font-semibold">Editar</button>
                        <button wire:click="eliminar({{ $c->id }})" wire:confirm="¿Eliminar esta cita?" class="text-red-600 font-semibold">Eliminar</button>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="p-8 text-center text-gray-500">No hay citas en este periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
