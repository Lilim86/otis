<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OTIS - CGI Bolivia</title>
    <!-- Carga los estilos que compilamos hace un rato -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <div class="relative h-screen w-full overflow-hidden">
        
        <!-- FONDO: Por ahora puse una imagen de edificios de prueba. Luego la cambiamos por tu imagen o video real -->
        <img src="https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?q=80&w=2070&auto=format&fit=crop"
             alt="Fondo OTIS" class="absolute inset-0 w-full h-full object-cover">

        <!-- Capa oscura semitransparente para que resalten las letras blancas -->
        <div class="absolute inset-0 bg-black/30"></div>

        <!-- BARRA DE NAVEGACIÓN SUPERIOR -->
        <header class="absolute top-0 left-0 w-full z-10 flex items-center justify-between px-8 py-0">
            
            <!-- Cajita del Logo (Azul OTIS) -->
            <div class="bg-marca-900 text-white p-5 text-center w-48 shadow-lg">
                <h1 class="text-4xl font-black tracking-wider">OTIS</h1>
                <p class="text-[0.65rem] uppercase mt-1 leading-tight font-semibold tracking-widest">
                    Distribuidor<br>Autorizado
                </p>
            </div>

            <!-- Menú central -->
            <nav class="hidden md:flex space-x-8 text-white font-medium items-center text-sm tracking-wide">
                <a href="#" class="text-red-500 flex items-center gap-1"><span class="text-xs">📍</span> Inicio</a>
                <a href="#" class="hover:text-gray-300 transition">Empresa ▾</a>
                <a href="#" class="hover:text-gray-300 transition">Productos ▾</a>
                <a href="#" class="hover:text-gray-300 transition">Servicios ▾</a>
                <a href="#" class="hover:text-gray-300 transition">Contactos</a>
            </nav>

            <!-- Botones de Login / Registro -->
            <div class="flex space-x-3 text-white text-sm">
                @if (Route::has('login'))
                    @auth
                        <!-- Si ya inició sesión, muestra el botón para ir al Panel -->
                        <a href="{{ url('/dashboard') }}" class="hover:bg-white hover:text-black border border-white font-semibold px-5 py-2 transition">Ir al Panel</a>
                    @else
                        <a href="{{ route('login') }}" class="hover:text-gray-300 font-semibold px-4 py-2 transition">Iniciar Sesión</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="bg-red-600 hover:bg-red-700 text-white font-semibold px-5 py-2 rounded-sm transition shadow-md">Registrarse</a>
                        @endif
                    @endauth
                @endif
            </div>
        </header>

        <!-- PIE DE PÁGINA -->
        <footer class="absolute bottom-0 w-full z-10 bg-white/40 backdrop-blur-sm text-gray-900 text-center py-2 text-[0.7rem] font-semibold tracking-widest border-t border-white/30">
            © 2026 CGI BOLIVIA. All Rights Reserved. *** Línea Gratuita 800-16-1002
        </footer>
    </div>
</body>
</html>