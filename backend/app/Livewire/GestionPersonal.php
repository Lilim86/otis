<?php

namespace App\Livewire;

use App\Models\Cargo;
use App\Models\Personal;
use App\Models\Profesion;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionPersonal extends Component
{
    public $personal_list;

    public $cargos;

    public $profesiones;

    public $personal_id;

    public $cargo_id;

    public $profesion_id;

    public $ci;

    public $nombres;

    public $apellidos;

    public $estado = 1;

    public function render()
    {
        $this->personal_list = Personal::with(['cargo', 'profesion'])->orderBy('apellidos', 'asc')->get();
        $this->cargos = Cargo::where('estado', 1)->orderBy('nombre', 'asc')->get();
        $this->profesiones = Profesion::where('estado', 1)->orderBy('nombre', 'asc')->get();

        return view('livewire.gestion-personal');
    }

    public function guardar()
    {
        $this->validate([
            'cargo_id' => 'required|exists:cargos,id',
            'profesion_id' => 'required|exists:profesiones,id',
            'ci' => 'required|string|unique:personal,ci'.($this->personal_id ? ','.$this->personal_id : ''),
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',

            'estado' => 'nullable|boolean',
        ]);

        Personal::updateOrCreate(
            ['id' => $this->personal_id],
            [
                'cargo_id' => $this->cargo_id,
                'profesion_id' => $this->profesion_id,
                'ci' => $this->ci,
                'nombres' => $this->nombres,
                'apellidos' => $this->apellidos,
                'estado' => $this->estado,
            ]
        );

        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $empleado = Personal::findOrFail($id);
        $this->personal_id = $empleado->id;
        $this->cargo_id = $empleado->cargo_id;
        $this->profesion_id = $empleado->profesion_id;
        $this->ci = $empleado->ci;
        $this->nombres = $empleado->nombres;
        $this->apellidos = $empleado->apellidos;
        $this->estado = $empleado->estado;
    }

    public function eliminar($id)
    {
        Personal::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['personal_id', 'cargo_id', 'profesion_id', 'ci', 'nombres', 'apellidos']);
        $this->estado = 1;
    }
}
