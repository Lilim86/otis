<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Control de privilegios de operación: cada ruta de módulo exige el permiso "modulo.operacion"
 * (ver, crear, editar, eliminar, restaurar, aprobar, anular). Solo se aplica con PERMISOS_ACTIVOS=true.
 * El Super Administrador (id 1) siempre pasa.
 */
class VerificaPermiso
{
    public static function permisoDe(Request $request): string
    {
        $segmentos = explode('/', trim(preg_replace('#^api/#', '', $request->path()), '/'));
        $modulo = $segmentos[0];
        $ultimo = end($segmentos);

        $operacion = match (true) {
            $ultimo === 'restaurar' => 'restaurar',
            $ultimo === 'aprobar' => 'aprobar',
            $ultimo === 'anular' => 'anular',
            in_array($ultimo, ['alta', 'baja'], true) => 'editar',
            $request->isMethod('GET') || $request->isMethod('HEAD') => 'ver',
            $request->isMethod('POST') => 'crear',
            $request->isMethod('DELETE') => 'eliminar',
            default => 'editar',
        };

        return "{$modulo}.{$operacion}";
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('sofia.permisos_activos')) {
            return $next($request);
        }

        $usuario = $request->user();
        $permiso = self::permisoDe($request);

        if (! $usuario || ! ($usuario->id === 1 || $usuario->can($permiso))) {
            return response()->json(['message' => "No tienes permiso para esta operación ({$permiso})."], 403);
        }

        return $next($request);
    }
}
