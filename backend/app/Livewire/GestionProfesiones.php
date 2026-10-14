<?php

namespace App\Livewire;

use App\Models\Profesion;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionProfesiones extends Component
{
    public $profesiones;

    public $profesion_id;

    public $nombre;

    public $estado = 1;

    public function render()
    {
        $this->profesiones = Profesion::orderBy('nombre', 'asc')->get();

        return view('livewire.gestion-profesiones');
    }

    public function guardar()
    {
        $this->validate(['nombre' => 'required|string|max:255|unique:profesiones,nombre'.($this->profesion_id ? ','.$this->profesion_id : ''),
            'estado' => 'nullable|boolean',
        ]);
        Profesion::updateOrCreate(['id' => $this->profesion_id], ['nombre' => $this->nombre, 'estado' => $this->estado]);
        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $profesion = Profesion::findOrFail($id);
        $this->profesion_id = $profesion->id;
        $this->nombre = $profesion->nombre;
        $this->estado = $profesion->estado;
    }

    public function eliminar($id)
    {
        Profesion::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['profesion_id', 'nombre']);
        $this->estado = 1;
    }
}
