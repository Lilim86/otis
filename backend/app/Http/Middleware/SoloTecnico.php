<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** La ruta del técnico es solo para usuarios vinculados a una persona del personal. */
class SoloTecnico
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->personal_id) {
            return response()->json(['message' => 'Esta cuenta no está vinculada a un técnico del personal.'], 403);
        }

        return $next($request);
    }
}
