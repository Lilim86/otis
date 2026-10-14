<?php

namespace App\Livewire;

use App\Models\Departamento;
use App\Models\Provincia;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionProvincias extends Component
{
    public $provincias;

    public $departamentos;

    public $provincia_id;

    public $departamento_id;

    public $nombre;

    public $estado = 1;

    public function render()
    {
        // Traemos las provincias con su departamento y las ordenamos alfabéticamente
        $this->provincias = Provincia::with('departamento')->orderBy('nombre', 'asc')->get();
        // Traemos solo los departamentos activos para el select
        $this->departamentos = Departamento::where('estado', 1)->orderBy('nombre', 'asc')->get();

        return view('livewire.gestion-provincias');
    }

    public function guardar()
    {
        $this->validate([
            'departamento_id' => 'required|exists:departamentos,id',
            'nombre' => 'required|string|max:255|unique:provincias,nombre'.($this->provincia_id ? ','.$this->provincia_id : ''),

            'estado' => 'nullable|boolean',
        ]);

        Provincia::updateOrCreate(
            ['id' => $this->provincia_id],
            [
                'departamento_id' => $this->departamento_id,
                'nombre' => $this->nombre,
                'estado' => $this->estado,
            ]
        );

        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $provincia = Provincia::findOrFail($id);
        $this->provincia_id = $provincia->id;
        $this->departamento_id = $provincia->departamento_id;
        $this->nombre = $provincia->nombre;
        $this->estado = $provincia->estado;
    }

    public function eliminar($id)
    {
        Provincia::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['provincia_id', 'departamento_id', 'nombre']);
        $this->estado = 1;
    }
}
