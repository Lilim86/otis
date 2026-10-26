<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UsuarioResource;
use App\Mail\CredencialesUsuarioMail;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UsuarioController extends CrudController
{
    protected string $modelo = User::class;

    protected string $resourceClase = UsuarioResource::class;

    protected array $buscar = ['name', 'email', 'username'];

    protected function reglas(?Model $registro): array
    {
        // Al editar se puede enviar solo lo que cambia; la contraseña no se modifica desde aquí.
        $presencia = $registro ? ['sometimes', 'required'] : ['required'];

        return [
            'name' => [...$presencia, 'string', 'max:255'],
            'email' => [...$presencia, 'email', 'max:255', $this->unico('users', 'email', $registro)],
            'empresa_id' => ['nullable', $this->existe('empresas')],
            'personal_id' => ['nullable', $this->existe('personal')],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ];
    }

    protected function datosParaGuardar(array $datos): array
    {
        unset($datos['roles']);

        return $datos;
    }

    protected function despuesDeGuardar(Model $registro, Request $request): void
    {
        if ($request->has('roles') && $registro->id !== 1) {
            $registro->syncRoles($request->input('roles', []));
        }
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate($this->reglas(null));
        $passwordTemporal = Str::random(8);

        // Si el correo falla, no se queda un usuario creado sin que le lleguen sus credenciales.
        $usuario = DB::transaction(function () use ($datos, $passwordTemporal) {
            $usuario = User::create([
                'name' => $datos['name'],
                'username' => explode('@', $datos['email'])[0],
                'email' => $datos['email'],
                'empresa_id' => $datos['empresa_id'] ?? null,
                'personal_id' => $datos['personal_id'] ?? null,
                'password' => Hash::make($passwordTemporal),
            ]);
            $usuario->syncRoles($datos['roles'] ?? []);

            Mail::to($usuario->email)->send(
                new CredencialesUsuarioMail($usuario->name, $usuario->email, $passwordTemporal)
            );

            return $usuario;
        });

        return $this->responder($usuario)->setStatusCode(201);
    }

    /** Dar de baja a un usuario también le cierra todas sus sesiones. */
    public function baja(string $id)
    {
        if ((int) $id === 1) {
            return response()->json(['message' => 'No puedes dar de baja al Super Administrador.'], 403);
        }

        $respuesta = parent::baja($id);

        $usuario = User::find($id);
        $usuario?->tokens()->delete();
        if ($usuario && config('session.driver') === 'database' && Schema::hasTable(config('session.table', 'sessions'))) {
            DB::table(config('session.table', 'sessions'))->where('user_id', $usuario->id)->delete();
        }

        return $respuesta;
    }

    protected function impedirEliminacion(Model $registro, Request $request): ?JsonResponse
    {
        if ($registro->id === 1) {
            return response()->json(['message' => 'No puedes eliminar al Super Administrador.'], 403);
        }

        if ($registro->id === $request->user()->id) {
            return response()->json(['message' => 'No puedes eliminar tu propia cuenta.'], 403);
        }

        return parent::impedirEliminacion($registro, $request);
    }
}
