<?php

namespace App\Services;

use App\Models\OtpCodigo;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    /**
     * Crea un código nuevo y anula los anteriores del usuario.
     * Devuelve el código en claro para enviarlo por correo; en la base solo queda su hash.
     */
    public function generar(User $usuario): string
    {
        OtpCodigo::where('user_id', $usuario->id)->whereNull('usado_en')->update(['usado_en' => now()]);

        $codigo = str_pad((string) random_int(0, 10 ** config('otp.digitos') - 1), config('otp.digitos'), '0', STR_PAD_LEFT);

        OtpCodigo::create([
            'user_id' => $usuario->id,
            'codigo_hash' => Hash::make($codigo),
            'expira_en' => now()->addMinutes(config('otp.minutos_vigencia')),
        ]);

        return $codigo;
    }

    /** Valida el código. Es de un solo uso y se bloquea tras demasiados intentos fallidos. */
    public function verificar(User $usuario, string $codigo): bool
    {
        $registro = OtpCodigo::where('user_id', $usuario->id)
            ->whereNull('usado_en')
            ->where('expira_en', '>', now())
            ->latest('id')
            ->first();

        if (! $registro || $registro->intentos >= config('otp.max_intentos')) {
            return false;
        }

        if (! Hash::check($codigo, $registro->codigo_hash)) {
            $registro->increment('intentos');

            return false;
        }

        $registro->update(['usado_en' => now()]);

        return true;
    }

    public function anular(User $usuario): void
    {
        OtpCodigo::where('user_id', $usuario->id)->whereNull('usado_en')->update(['usado_en' => now()]);
    }
}
