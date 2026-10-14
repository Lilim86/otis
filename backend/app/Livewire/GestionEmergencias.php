<?php

namespace App\Livewire;

use App\Models\Ascensor;
use App\Models\Emergencia;
use Livewire\Component;

class GestionEmergencias extends Component
{
    public $ascensor_id = '';

    public $gravedad = 'Crítica';

    public $descripcion_fallo;

    public function guardar()
    {
        $this->validate([
            'ascensor_id' => 'required|exists:ascensores,id',
            'gravedad' => 'required',
            'descripcion_fallo' => 'required',
        ]);

        Emergencia::create([
            'ascensor_id' => $this->ascensor_id,
            'gravedad' => $this->gravedad,
            'descripcion_fallo' => $this->descripcion_fallo,
            'situacion' => 'Reportado',
        ]);

        $this->reset(['ascensor_id', 'descripcion_fallo']);
        $this->gravedad = 'Crítica';
        session()->flash('mensaje', 'Emergencia registrada y validada con éxito.');
    }

    public function render()
    {
        $emergencias = Emergencia::with('ascensor.zona')->get();
        $ascensores = Ascensor::with('zona')->get();

        return view('livewire.gestion-emergencias', compact('emergencias', 'ascensores'))
            ->layout('layouts.app');
    }
}
