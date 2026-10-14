<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsuarioResource;
use App\Services\AccesoService;
use App\Services\AutenticacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Login por token Bearer, para clientes que no usan la sesión por cookie
 * (Postman, scripts, apps móviles). El frontend de Vue usa la cookie de Sanctum.
 */
class TokenController extends Controller
{
    public function store(Request $request, AutenticacionService $autenticacion): JsonResponse
    {
        $datos = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        if (config('otp.requerido')) {
            return response()->json([
                'message' => 'Este sistema requiere verificación por código. Usa /api/auth/otp/solicitar y /api/auth/otp/verificar.',
            ], 403);
        }

        try {
            $usuario = $autenticacion->usuarioConCredenciales($datos['username'], $datos['password']);
        } catch (ValidationException $e) {
            app(AccesoService::class)->registrar('FALLO', null, $datos['username'], $request);
            throw $e;
        }

        // Este método no pide el segundo factor, así que no se permite a cuentas que lo tienen activado.
        if ($usuario->two_factor_secret) {
            throw ValidationException::withMessages(['username' => 'Esta cuenta usa verificación en dos pasos; inicia sesión desde la aplicación.']);
        }

        app(AccesoService::class)->registrar('TOKEN', $usuario, null, $request);

        return response()->json([
            'token' => $usuario->createToken($datos['device_name'] ?? 'api')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UsuarioResource($usuario),
        ]);
    }

    /** Revoca el token con el que se hizo la petición. */
    public function destroy(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(null, 204);
    }
}
