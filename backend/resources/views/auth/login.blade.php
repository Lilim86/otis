<x-guest-layout>
    <x-authentication-card>
        <div class="mb-7">
            <h2 class="font-display text-3xl font-semibold text-marca-900">Bienvenido</h2>
            <div class="mt-2 h-0.5 w-10 bg-oro-500"></div>
            <p class="mt-3 text-sm text-gray-500">Ingresa con tu usuario o correo para continuar.</p>
        </div>

        @session('status')
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ $value }}</div>
        @endsession

        @if ($errors->any())
            <div class="mb-5 rounded-lg bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-800" role="alert">
                <p class="font-semibold">No pudimos iniciar tu sesión</p>
                <ul class="mt-1 list-disc ps-5">@foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5"
              x-data="{ usuario: @js(old('username', '')), clave: '', intento: false }"
              @submit="intento = true; if (!usuario.trim() || clave.length < 1) { $event.preventDefault() }">
            @csrf

            <div>
                <x-label for="username" value="Usuario o correo electrónico" />
                <x-input id="username" class="block mt-1 w-full" type="text" name="username" x-model="usuario" required autofocus autocomplete="username"
                         placeholder="Ej: jperez o jperez@empresa.com"
                         x-bind:class="intento && !usuario.trim() ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-300' : ''" />
                <p x-show="intento && !usuario.trim()" x-cloak class="mt-1 text-xs text-rose-600">Escribe tu usuario o correo.</p>
                @error('username') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-label for="password" value="{{ __('Password') }}" />
                <x-password-input id="password" class="mt-1" name="password" x-model="clave" required autocomplete="current-password" placeholder="Tu contraseña"
                                  x-bind:class="intento && !clave ? 'border-rose-400' : ''" />
                <p x-show="intento && !clave" x-cloak class="mt-1 text-xs text-rose-600">Escribe tu contraseña.</p>
                @error('password') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-between">
                <label for="remember_me" class="flex items-center">
                    <x-checkbox id="remember_me" name="remember" />
                    <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                </label>
                @if (Route::has('password.request'))
                    <a class="text-sm text-marca-600 hover:text-oro-600 underline-offset-2 hover:underline rounded-md focus:outline-none focus:ring-2 focus:ring-oro-400" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>

            <x-button class="w-full !py-3 !from-oro-500 !to-oro-400 !text-marca-950 hover:!from-oro-400 hover:!to-oro-300 shadow-md shadow-oro-500/30">
                {{ __('Log in') }}
            </x-button>
        </form>
    </x-authentication-card>
</x-guest-layout>
