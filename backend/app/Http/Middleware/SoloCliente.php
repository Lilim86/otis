<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** El portal es solo para usuarios vinculados a una empresa cliente. */
class SoloCliente
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->empresa_id) {
            return response()->json(['message' => 'Esta cuenta no está vinculada a una empresa cliente.'], 403);
        }

        return $next($request);
    }
}
