<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsuarioResource;
use App\Mail\OtpCodigoMail;
use App\Models\User;
use App\Services\AccesoService;
use App\Services\AutenticacionService;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Inicio de sesión en dos pasos:
 *   1. solicitar: usuario + contraseña -> se envía un código al correo.
 *   2. verificar: usuario + código -> inicia la sesión (Vue, por cookie) o devuelve un token Bearer.
 */
class OtpController extends Controller
{
    public function __construct(
        private AutenticacionService $autenticacion,
        private OtpService $otp,
        private AccesoService $accesos,
    ) {}

    public function solicitar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        try {
            $usuario = $this->autenticacion->usuarioConCredenciales($datos['username'], $datos['password']);
        } catch (ValidationException $e) {
            $this->accesos->registrar('FALLO', null, $datos['username'], $request);
            throw $e;
        }
        // Con el código desactivado (solo desarrollo) basta usuario y contraseña: se inicia la sesión de una vez.
        if (! config('otp.requerido') && $request->hasSession()) {
            Auth::guard('web')->login($usuario);
            $request->session()->regenerate();

            return response()->json(['sin_codigo' => true, 'user' => new UsuarioResource($usuario)]);
        }

        $this->accesos->registrar('OTP_SOLICITADO', $usuario, null, $request);
        $codigo = $this->otp->generar($usuario);

        try {
            Mail::to($usuario->email)->send(
                new OtpCodigoMail($usuario->name, $codigo, config('otp.minutos_vigencia'))
            );
        } catch (\Throwable $e) {
            report($e);
            $this->otp->anular($usuario);

            return response()->json(['message' => 'No se pudo enviar el código por correo. Inténtalo más tarde.'], 503);
        }

        return response()->json([
            'message' => 'Te enviamos un código de verificación al correo.',
            'correo' => $this->enmascarar($usuario->email),
            'expira_en_segundos' => config('otp.minutos_vigencia') * 60,
        ]);
    }

    public function verificar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'username' => ['required', 'string'],
            'codigo' => ['required', 'digits:'.config('otp.digitos')],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $usuario = User::conLogin($datos['username'])->first();

        if (! $usuario || ! $usuario->estado || ! $this->otp->verificar($usuario, $datos['codigo'])) {
            $this->accesos->registrar('OTP_FALLO', $usuario, $datos['username'], $request);
            throw ValidationException::withMessages(['codigo' => 'El código es incorrecto o ya venció.']);
        }

        // Frontend de Vue (sesión por cookie de Sanctum): se inicia la sesión.
        if ($request->hasSession()) {
            Auth::guard('web')->login($usuario);
            $request->session()->regenerate();

            return response()->json(['user' => new UsuarioResource($usuario)]);
        }

        // Postman, scripts, apps: se entrega un token Bearer.
        $this->accesos->registrar('TOKEN', $usuario, null, $request);

        return response()->json([
            'token' => $usuario->createToken($datos['device_name'] ?? 'api')->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UsuarioResource($usuario),
        ]);
    }

    /** juan.perez@gmail.com -> ju*****@gmail.com */
    private function enmascarar(string $correo): string
    {
        [$local, $dominio] = explode('@', $correo);

        return Str::substr($local, 0, 2).str_repeat('*', max(strlen($local) - 2, 3)).'@'.$dominio;
    }
}
