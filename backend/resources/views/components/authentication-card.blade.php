<div class="min-h-screen grid lg:grid-cols-2 bg-marfil">
    {{-- Panel de marca --}}
    <aside class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-gradient-to-br from-marca-950 via-marca-900 to-marca-700 text-white p-14">
        <div class="pointer-events-none absolute -top-32 -right-32 size-96 rounded-full border border-oro-400/30"></div>
        <div class="pointer-events-none absolute -top-16 -right-16 size-64 rounded-full border border-oro-400/20"></div>
        <div class="pointer-events-none absolute -bottom-40 -left-24 size-[28rem] rounded-full bg-oro-500/10 blur-3xl"></div>

        <div class="relative">
            <div class="font-display text-5xl font-extrabold tracking-[0.3em] text-oro-300">OTIS</div>
            <div class="mt-1 text-xs uppercase tracking-[0.5em] text-oro-200/70">LILIM</div>
        </div>

        <div class="relative max-w-md">
            <div class="h-px w-16 bg-oro-400 mb-8"></div>
            <h1 class="font-display text-4xl font-semibold leading-tight">Gestión integral de ascensores y servicios.</h1>
            <p class="mt-5 text-marca-100/80 leading-relaxed">Contratos, mantenimientos, almacenes, cobranzas y agenda gerencial en un solo lugar, con el respaldo de la Compañía Comercial General Industrial Ltda.</p>
            <ul class="mt-8 space-y-3 text-sm text-marca-100/90">
                @foreach(['Control por roles y privilegios', 'Reportes combinados por regional', 'Acceso protegido y auditado'] as $punto)
                    <li class="flex items-center gap-3"><span class="size-1.5 rounded-full bg-oro-400"></span>{{ $punto }}</li>
                @endforeach
            </ul>
        </div>

        <div class="relative text-xs text-marca-200/60">© {{ date('Y') }} Compañía Comercial General Industrial Ltda.</div>
    </aside>

    {{-- Formulario --}}
    <main class="flex flex-col items-center justify-center px-6 py-10">
        <div class="lg:hidden mb-8 text-center">
            <div class="font-display text-4xl font-extrabold tracking-[0.3em] text-marca-900">OTIS</div>
            <div class="mt-1 h-0.5 w-12 mx-auto bg-oro-500"></div>
        </div>

        <div class="w-full max-w-md bg-white rounded-2xl shadow-xl shadow-marca-900/10 border border-oro-200/60 px-8 py-9">
            {{ $slot }}
        </div>
        <p class="mt-6 text-xs text-gray-400">Sistema OTIS · LILIM</p>
    </main>
</div>
