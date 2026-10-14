<?php

namespace App\Livewire;

use App\Models\Cargo;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionCargos extends Component
{
    public $cargos;

    public $cargo_id;

    public $nombre;

    public $estado = 1;

    public function render()
    {
        $this->cargos = Cargo::orderBy('nombre', 'asc')->get();

        return view('livewire.gestion-cargos');
    }

    public function guardar()
    {
        $this->validate(['nombre' => 'required|string|max:255|unique:cargos,nombre'.($this->cargo_id ? ','.$this->cargo_id : ''),
            'estado' => 'nullable|boolean',
        ]);

        Cargo::updateOrCreate(['id' => $this->cargo_id], ['nombre' => $this->nombre, 'estado' => $this->estado]);
        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $cargo = Cargo::findOrFail($id);
        $this->cargo_id = $cargo->id;
        $this->nombre = $cargo->nombre;
        $this->estado = $cargo->estado;
    }

    public function eliminar($id)
    {
        Cargo::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['cargo_id', 'nombre']);
        $this->estado = 1;
    }
}
