<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AutenticacionService
{
    /**
     * Devuelve el usuario si usuario y contraseña son correctos y está activo.
     * Los usuarios eliminados no se encuentran (SoftDeletes).
     */
    public function usuarioConCredenciales(string $username, string $password): User
    {
        $usuario = User::conLogin($username)->first();

        if (! $usuario || ! Hash::check($password, $usuario->password)) {
            throw ValidationException::withMessages(['username' => 'Las credenciales no son correctas.']);
        }

        if (! $usuario->estado) {
            throw ValidationException::withMessages(['username' => 'El usuario está inactivo.']);
        }

        return $usuario;
    }
}
