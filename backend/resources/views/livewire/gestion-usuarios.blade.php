<div class="p-6 max-w-7xl mx-auto">
    
    @if (session()->has('mensaje'))
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 shadow-sm font-bold" role="alert">
            <p>{{ session('mensaje') }}</p>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 shadow-sm font-bold" role="alert">
            <p>{{ session('error') }}</p>
        </div>
    @endif

    <div class="bg-white p-6 rounded-lg shadow-md mb-8 border-t-4 border-oro-500">
        <h2 class="text-xl font-bold mb-6 text-gray-800">{{ $editandoId ? 'Modificar Usuario' : 'Alta de Nuevos Usuarios (Empleados)' }}</h2>
        <form wire:submit="guardar" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre Completo del Empleado</label>
                <input type="text" wire:model="name" placeholder="Ej: Juan Perez" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500">
                @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Correo Electrónico</label>
                <input type="email" wire:model="email" placeholder="Ej: juan.perez@gmail.com" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500">
                @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Rol (perfil de privilegios)</label>
                <select wire:model="rol" class="w-full border-gray-300 rounded-md shadow-sm focus:border-oro-500" @disabled($editandoId === 1)>
                    <option value="">— Sin rol —</option>
                    @foreach($roles as $nombreRol)
                        <option value="{{ $nombreRol }}">{{ $nombreRol }}</option>
                    @endforeach
                </select>
                @error('rol') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>

            @unless($editandoId)
            <div class="md:col-span-2 text-sm text-gray-500 mb-2">
                * El sistema generará una contraseña segura automáticamente y se la enviará a este correo.
            </div>
            @endunless

            <div class="md:col-span-2 flex justify-end gap-3">
                @if($editandoId)
                    <button type="button" wire:click="limpiarCampos" class="px-6 py-3 rounded font-bold border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
                @endif
                <button type="submit" class="bg-marca-900 text-white px-8 py-3 rounded font-bold hover:bg-marca-800 transition flex items-center">
                    <span>{{ $editandoId ? 'Guardar Cambios' : 'Crear Cuenta y Enviar Correo' }}</span>
                    <span wire:loading wire:target="guardar" class="ml-2">⏳...</span>
                </button>
            </div>
        </form>
    </div>

    <div class="flex justify-end mb-3">
        <label class="text-sm text-gray-600 flex items-center gap-2">
            <input type="checkbox" wire:model.live="verEliminados" class="rounded"> Ver papelera (eliminados)
        </label>
    </div>

    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50 border-b">
                <tr><th class="p-4 text-xs font-semibold text-gray-500 uppercase">NOMBRE DEL USUARIO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">CORREO REGISTRADO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">ROL</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">ESTADO</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase">FECHA DE ALTA</th><th class="p-4 text-xs font-semibold text-gray-500 uppercase text-right">ACCIONES</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($usuarios as $user)
                <tr class="hover:bg-gray-50">
                    <td class="p-4 text-sm font-bold text-marca-900">{{ $user->name }}</td>
                    <td class="p-4 text-sm text-gray-700">{{ $user->email }}</td>
                    <td class="p-4 text-sm text-gray-700">
                        @if($user->id == 1)<span class="font-bold">Super Admin</span>
                        @else {{ $user->roles->pluck('name')->join(', ') ?: '—' }} @endif
                    </td>
                    <td class="p-4 text-sm">
                        <span class="{{ $user->estado ? 'text-green-700' : 'text-red-600' }} font-semibold">{{ $user->estado ? 'Activo' : 'De baja' }}</span>
                    </td>
                    <td class="p-4 text-sm text-gray-600">{{ $user->created_at->format('d/m/Y') }}</td>
                    <td class="p-4 text-sm text-right space-x-3">
                        @if($user->trashed())
                            <button wire:click="restaurar({{ $user->id }})" class="text-green-700 font-semibold hover:underline">Restaurar</button>
                        @else
                            <button wire:click="editar({{ $user->id }})" class="text-marca-800 font-semibold hover:underline">Modificar</button>
                            @if($user->id != 1)
                                <button wire:click="alternarEstado({{ $user->id }})" class="text-amber-600 font-semibold hover:underline">{{ $user->estado ? 'Dar de baja' : 'Dar de alta' }}</button>
                                <button wire:click="eliminar({{ $user->id }})" wire:confirm="¿Eliminar a {{ $user->name }}? Podrás recuperarlo desde la papelera." class="text-red-600 font-semibold hover:underline">Eliminar</button>
                            @else
                                <span class="text-gray-400 font-bold">Super Admin</span>
                            @endif
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>