<?php

namespace App\Services;

use App\Models\Acceso;
use App\Models\User;
use Illuminate\Http\Request;

class AccesoService
{
    public function registrar(string $evento, ?User $usuario = null, ?string $username = null, ?Request $request = null): void
    {
        $request ??= request();

        Acceso::create([
            'user_id' => $usuario?->id,
            'username_intentado' => $username ?? $usuario?->username,
            'evento' => $evento,
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
