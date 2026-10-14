<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** Roles y permisos por operación. Solo el Super Administrador o quien tenga "seguridad.administrar". */
class RolController extends Controller
{
    private const PROTEGIDO = 'Administrador';

    public function index(): JsonResponse
    {
        return response()->json(['data' => Role::with('permissions:id,name')->withCount('users')->orderBy('name')->get()->map(fn ($r) => $this->formato($r))]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate($this->reglas());
        $rol = Role::create(['name' => $datos['name'], 'guard_name' => 'web']);
        $rol->syncPermissions($datos['permisos'] ?? []);

        return response()->json(['data' => $this->formato($rol->load('permissions'))], 201);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => $this->formato(Role::with('permissions')->withCount('users')->findOrFail($id))]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $rol = Role::findOrFail($id);
        abort_if($rol->name === self::PROTEGIDO, 403, 'El rol Administrador no se puede modificar.');

        $datos = $request->validate($this->reglas($rol));
        $rol->update(['name' => $datos['name']]);
        $rol->syncPermissions($datos['permisos'] ?? []);

        return response()->json(['data' => $this->formato($rol->load('permissions'))]);
    }

    public function destroy(string $id): JsonResponse
    {
        $rol = Role::findOrFail($id);
        abort_if($rol->name === self::PROTEGIDO, 403, 'El rol Administrador no se puede eliminar.');

        if ($rol->users()->exists()) {
            return response()->json(['message' => 'No se puede eliminar: hay usuarios con este rol.'], 409);
        }

        $rol->delete();

        return response()->json(null, 204);
    }

    /** Permisos agrupados por módulo, para armar la matriz de privilegios. */
    public function permisos(): JsonResponse
    {
        $agrupados = Permission::orderBy('name')->pluck('name')->groupBy(fn ($n) => explode('.', $n)[0])
            ->map(fn ($nombres, $modulo) => ['modulo' => $modulo, 'permisos' => $nombres->values()])->values();

        return response()->json(['data' => $agrupados]);
    }

    /** Reemplaza los roles de un usuario. */
    public function asignar(Request $request, string $usuario): JsonResponse
    {
        $datos = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);

        $user = User::findOrFail($usuario);
        abort_if($user->id === 1, 403, 'El Super Administrador ya tiene todos los privilegios.');
        $user->syncRoles($datos['roles']);

        return response()->json(['data' => ['id' => $user->id, 'roles' => $user->roles->pluck('name')]]);
    }

    private function reglas(?Role $rol = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($rol?->id)],
            'permisos' => ['nullable', 'array'],
            'permisos.*' => ['string', Rule::exists('permissions', 'name')],
        ];
    }

    private function formato(Role $rol): array
    {
        return [
            'id' => $rol->id,
            'name' => $rol->name,
            'permisos' => $rol->permissions->pluck('name')->sort()->values(),
            'usuarios' => $rol->users_count ?? null,
        ];
    }
}
