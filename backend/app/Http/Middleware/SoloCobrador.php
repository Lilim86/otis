<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** El portal del cobrador es solo para usuarios con el rol Cobrador enlazados a su persona cobradora. */
class SoloCobrador
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->esCobrador()) {
            return response()->json(['message' => 'Esta cuenta no está vinculada a un cobrador.'], 403);
        }

        return $next($request);
    }
}
