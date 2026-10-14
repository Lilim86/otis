<?php

namespace App\Livewire;

use App\Models\ClasificadorTecnico;
use Livewire\Component;

class GestionClasificadoresTecnicos extends Component
{
    public $nombre;

    public $descripcion;

    public function guardar()
    {
        $this->validate([
            'nombre' => 'required|unique:clasificadores_tecnicos,nombre',
            'descripcion' => 'nullable',
        ]);

        ClasificadorTecnico::create([
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
        ]);

        $this->reset(['nombre', 'descripcion']);
        session()->flash('mensaje', 'Clasificador técnico registrado exitosamente.');
    }

    public function render()
    {
        $clasificadores = ClasificadorTecnico::all();

        return view('livewire.gestion-clasificadores-tecnicos', compact('clasificadores'))
            ->layout('layouts.app');
    }
}
