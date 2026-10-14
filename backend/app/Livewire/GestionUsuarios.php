<?php

namespace App\Livewire;

use App\Mail\CredencialesUsuarioMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
class GestionUsuarios extends Component
{
    public $name;

    public $email;

    /** Rol que se le asigna al crear la cuenta. */
    public $rol = '';

    /** Usuario que se está editando (null = formulario de alta). */
    public ?int $editandoId = null;

    public bool $editandoEstado = true;

    public bool $verEliminados = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('administrar-seguridad'), 403);
    }

    public function render()
    {
        $usuarios = User::with('roles:id,name')
            ->when($this->verEliminados, fn ($q) => $q->onlyTrashed())
            ->orderByDesc('id')
            ->get();

        return view('livewire.gestion-usuarios', [
            'usuarios' => $usuarios,
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function guardar()
    {
        $this->validate($this->reglas());

        if ($this->editandoId) {
            return $this->actualizar();
        }

        $passwordTemporal = Str::random(8);

        // Si el correo falla no queda una cuenta creada sin que le lleguen sus credenciales.
        DB::transaction(function () use ($passwordTemporal) {
            $nuevo = User::create([
                'name' => $this->name,
                'username' => explode('@', $this->email)[0],
                'email' => $this->email,
                'password' => Hash::make($passwordTemporal),
            ]);
            $nuevo->syncRoles($this->rol ? [$this->rol] : []);

            Mail::to($nuevo->email)->send(new CredencialesUsuarioMail($nuevo->name, $nuevo->email, $passwordTemporal));
        });

        $this->limpiarCampos();
        session()->flash('mensaje', '¡Cuenta creada, rol asignado y credenciales enviadas por correo!');
    }

    public function editar(int $id): void
    {
        $usuario = User::with('roles:id,name')->findOrFail($id);

        $this->editandoId = $usuario->id;
        $this->name = $usuario->name;
        $this->email = $usuario->email;
        $this->rol = $usuario->roles->first()?->name ?? '';
        $this->editandoEstado = (bool) $usuario->estado;
        $this->resetValidation();
    }

    public function eliminar(int $id): void
    {
        if ($id === 1) {
            session()->flash('error', 'No puedes eliminar al Super Administrador.');

            return;
        }
        if ($id === auth()->id()) {
            session()->flash('error', 'No puedes eliminar tu propia cuenta.');

            return;
        }

        $usuario = User::findOrFail($id);
        $this->cerrarSesiones($usuario);
        $usuario->delete();

        session()->flash('mensaje', 'Usuario eliminado. Puedes recuperarlo desde la papelera.');
    }

    public function restaurar(int $id): void
    {
        User::onlyTrashed()->findOrFail($id)->restore();
        session()->flash('mensaje', 'Usuario restaurado.');
    }

    /** Baja/alta lógica: el usuario no puede entrar pero conserva su historial y su rol. */
    public function alternarEstado(int $id): void
    {
        if ($id === 1 || $id === auth()->id()) {
            session()->flash('error', 'No puedes dar de baja esa cuenta.');

            return;
        }

        $usuario = User::findOrFail($id);
        $usuario->update(['estado' => ! $usuario->estado]);
        if (! $usuario->estado) {
            $this->cerrarSesiones($usuario);
        }
    }

    public function limpiarCampos(): void
    {
        $this->reset(['name', 'email', 'rol', 'editandoId']);
        $this->editandoEstado = true;
        $this->resetValidation();
    }

    private function actualizar(): void
    {
        $usuario = User::findOrFail($this->editandoId);

        $usuario->update(['name' => $this->name, 'email' => $this->email]);

        // El Super Administrador tiene todos los privilegios sin necesitar rol.
        if ($usuario->id !== 1) {
            $usuario->syncRoles($this->rol ? [$this->rol] : []);
        }

        $this->limpiarCampos();
        session()->flash('mensaje', 'Usuario actualizado.');
    }

    private function reglas(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editandoId)],
            'rol' => ['nullable', 'string', Rule::exists('roles', 'name')],
        ];
    }

    private function cerrarSesiones(User $usuario): void
    {
        $usuario->tokens()->delete();
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $usuario->id)->delete();
        }
    }
}
