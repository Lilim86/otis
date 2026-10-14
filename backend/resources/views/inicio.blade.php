<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>OTIS · LILIM</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600|playfair-display:600,800&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-marca-950 text-white overflow-hidden">
        {{-- Video de fondo (el mismo de la web de la empresa). Si el navegador no puede reproducirlo se ve la imagen fija. --}}
        <video id="fondo" class="fixed inset-0 size-full object-cover" autoplay muted loop playsinline preload="auto" poster="{{ asset('media/otis-fondo.jpg') }}" aria-hidden="true">
            <source src="{{ asset('media/otis-fondo.mp4') }}" type="video/mp4">
        </video>
        <div class="fixed inset-0 bg-gradient-to-b from-marca-950/70 via-marca-950/35 to-marca-950/85"></div>

        <div class="relative min-h-screen flex flex-col">
            <header class="flex items-center justify-between px-6 sm:px-12 pt-0">
                <img src="{{ asset('media/otis-logo.png') }}" alt="OTIS Distribuidor Autorizado" class="h-24 sm:h-28 w-auto">
                <a href="{{ route('login') }}" class="rounded-full border border-oro-300/70 px-6 py-2 text-sm font-semibold tracking-wide text-oro-200 backdrop-blur hover:bg-oro-400 hover:text-marca-950 transition">
                    Ingresar
                </a>
            </header>

            <main class="flex-1 flex items-center px-6 sm:px-12">
                <div class="max-w-2xl">
                    <p class="text-xs sm:text-sm uppercase tracking-[0.5em] text-oro-300">Sistema de gestión</p>
                    <h1 class="mt-4 font-display text-6xl sm:text-8xl font-extrabold tracking-[0.12em] text-white drop-shadow-lg">LILIM</h1>
                    <div class="mt-5 h-0.5 w-20 bg-oro-400"></div>
                    <p class="mt-6 text-lg sm:text-xl text-marca-50/90 leading-relaxed">
                        Contratos, mantenimientos, almacenes, cobranzas y agenda gerencial de ascensores OTIS, en un solo lugar.
                    </p>
                    <div class="mt-10 flex flex-wrap items-center gap-4">
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-3 rounded-lg bg-gradient-to-r from-oro-500 to-oro-400 px-8 py-4 text-sm font-bold uppercase tracking-widest text-marca-950 shadow-xl shadow-oro-500/30 hover:from-oro-400 hover:to-oro-300 transition">
                            Ingresar al sistema
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                        </a>
                        <span class="text-sm text-marca-100/70">Acceso solo para personal autorizado</span>
                    </div>
                </div>
            </main>

            <footer class="px-6 sm:px-12 py-4 bg-marca-950/60 backdrop-blur text-center text-xs sm:text-sm text-marca-100/80">
                © {{ date('Y') }} CGI BOLIVIA. All Rights Reserved. *** Línea Gratuita 800-16-1002
            </footer>
        </div>

        <script>
            // Quien prefiere menos movimiento ve solo la imagen fija.
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                const v = document.getElementById('fondo'); v.pause(); v.removeAttribute('autoplay');
            }
        </script>
    </body>
</html>
