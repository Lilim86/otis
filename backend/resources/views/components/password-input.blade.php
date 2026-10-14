{{-- Campo de contraseña con "ojito" para mostrar u ocultar lo que se escribe. Acepta los mismos atributos que x-input. --}}
@props(['disabled' => false])
<div {{ $attributes->only('class')->merge(['class' => 'relative']) }}>
    <input
        {{ $disabled ? 'disabled' : '' }}
        type="password"
        {!! $attributes->except('class')->merge(['class' => 'block w-full pe-11 border-gray-300 focus:border-oro-500 focus:ring-oro-400 rounded-md shadow-sm']) !!}
    >
    {{-- JS simple y no Alpine: así el x-ref del campo sigue visible para los modales de Jetstream. --}}
    <button type="button" tabindex="-1" title="Mostrar u ocultar contraseña" aria-label="Mostrar u ocultar contraseña"
        onclick="const i = this.parentElement.querySelector('input'); const mostrar = i.type === 'password'; i.type = mostrar ? 'text' : 'password'; this.querySelector('[data-ojo-abierto]').classList.toggle('hidden', mostrar); this.querySelector('[data-ojo-tachado]').classList.toggle('hidden', !mostrar);"
        class="absolute inset-y-0 end-0 px-3 flex items-center text-gray-400 hover:text-oro-600 focus:outline-none">
        <svg data-ojo-abierto xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.04 12.32a1 1 0 0 1 0-.64C3.42 7.51 7.36 4.5 12 4.5c4.64 0 8.57 3.01 9.96 7.18.07.21.07.43 0 .64C20.58 16.49 16.64 19.5 12 19.5c-4.64 0-8.57-3.01-9.96-7.18Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
        </svg>
        <svg data-ojo-tachado xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-5 hidden">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.48 10.48 0 0 0 1.93 12c1.29 3.9 4.94 7.5 10.07 7.5 1.77 0 3.4-.43 4.83-1.17M6.23 6.23A10.45 10.45 0 0 1 12 4.5c5.13 0 8.78 3.6 10.07 7.5a10.5 10.5 0 0 1-4.3 5.54M6.23 6.23 3 3m3.23 3.23 3.65 3.65m7.89 7.89L21 21m-3.23-3.23-3.65-3.65m0 0a3 3 0 1 0-4.24-4.24m4.24 4.24L9.88 9.88" />
        </svg>
    </button>
</div>
