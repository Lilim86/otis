<?php

namespace App\Providers;

use App\Models\User;
use App\Services\AccesoService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider; // <-- Agrega esta línea arriba

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ¡NUESTRO COMANDO MÁGICO DE AUDITORÍA!
        Blueprint::macro('auditoria', function () {
            $this->unsignedBigInteger('creado_por')->nullable();
            $this->unsignedBigInteger('actualizado_por')->nullable();
            // Si quieres que estado se cree aquí, descomenta la siguiente línea:
            // $this->boolean('estado')->default(true);
            $this->softDeletes(); // Crea 'deleted_at'
            $this->timestamps();  // Crea 'created_at' y 'updated_at'
        });

        // Ubicación geográfica completa (país hasta vía de acceso), igual que el SOFIA.
        Blueprint::macro('ubicacion', function () {
            $this->foreignId('pais_id')->nullable()->constrained('paises')->nullOnDelete();
            $this->foreignId('departamento_id')->nullable()->constrained('departamentos')->nullOnDelete();
            $this->foreignId('provincia_id')->nullable()->constrained('provincias')->nullOnDelete();
            $this->foreignId('municipio_id')->nullable()->constrained('municipios')->nullOnDelete();
            $this->foreignId('zona_id')->nullable()->constrained('zonas')->nullOnDelete();
            $this->foreignId('via_acceso_id')->nullable()->constrained('via_accesos')->nullOnDelete();
        });

        // Flujo REG -> APR / ANL (Estado Registro del SOFIA).
        Blueprint::macro('aprobacion', function () {
            $this->string('estado_registro', 3)->default('REG')->index();
        });

        // El Super Administrador (id 1) puede todo; los demás dependen de sus roles y permisos.
        Gate::before(fn (User $usuario) => $usuario->id === 1 ? true : null);
        Gate::define('administrar-seguridad', fn (User $usuario) => $usuario->id === 1 || $usuario->can('seguridad.administrar'));

        // Menú y pantallas web: se ve un módulo si el rol tiene "modulo.ver" de alguno de los indicados.
        Gate::define('modulo', fn (User $usuario, ...$modulos) => ! config('sofia.permisos_activos')
            || collect($modulos)->flatten()->contains(fn ($m) => $usuario->can("{$m}.ver")));

        // Control de accesos: bitácora de ingresos, salidas y fallos.
        Event::listen(Login::class, fn (Login $e) => app(AccesoService::class)->registrar('LOGIN', $e->user));
        Event::listen(Logout::class, fn (Logout $e) => $e->user ? app(AccesoService::class)->registrar('LOGOUT', $e->user) : null);
        Event::listen(Failed::class, fn (Failed $e) => app(AccesoService::class)->registrar('FALLO', $e->user, $e->credentials['username'] ?? $e->credentials['email'] ?? null));
    }
}
