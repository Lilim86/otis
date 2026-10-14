<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/** Pantallas web: exige poder ver al menos uno de los módulos indicados (ver Gate "modulo"). */
class VerificaModulo
{
    public function handle(Request $request, Closure $next, string ...$modulos): Response
    {
        abort_unless(Gate::allows('modulo', $modulos), 403, 'No tienes acceso a este módulo.');

        return $next($request);
    }
}
