<?php

namespace App\Livewire;

use App\Models\Edificio;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionEdificios extends Component
{
    public $edificios;

    public $edificio_id;

    public $codigo_edificio;

    public $denominacion;

    public $tipo_edificacion = 'EDIFICIO DE DEPARTAMENTOS';

    public $estado = 1;

    public function render()
    {
        $this->edificios = Edificio::orderBy('codigo_edificio', 'asc')->get();

        return view('livewire.gestion-edificios');
    }

    public function guardar()
    {
        $this->validate([
            'codigo_edificio' => 'required|string|unique:edificios,codigo_edificio'.($this->edificio_id ? ','.$this->edificio_id : ''),
            'denominacion' => 'required|string|max:255',
            'tipo_edificacion' => 'required|string|max:100',

            'estado' => 'nullable|boolean',
        ]);

        Edificio::updateOrCreate(
            ['id' => $this->edificio_id],
            ['codigo_edificio' => $this->codigo_edificio, 'denominacion' => $this->denominacion, 'tipo_edificacion' => $this->tipo_edificacion, 'estado' => $this->estado]
        );
        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $edi = Edificio::findOrFail($id);
        $this->edificio_id = $edi->id;
        $this->codigo_edificio = $edi->codigo_edificio;
        $this->denominacion = $edi->denominacion;
        $this->tipo_edificacion = $edi->tipo_edificacion;
        $this->estado = $edi->estado;
    }

    public function eliminar($id)
    {
        Edificio::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['edificio_id', 'codigo_edificio', 'denominacion']);
        $this->tipo_edificacion = 'EDIFICIO DE DEPARTAMENTOS';
        $this->estado = 1;
    }
}
