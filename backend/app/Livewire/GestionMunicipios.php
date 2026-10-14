<?php

namespace App\Livewire;

use App\Models\Municipio;
use App\Models\Provincia;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionMunicipios extends Component
{
    public $municipios;

    public $provincias;

    public $municipio_id;

    public $provincia_id;

    public $nombre;

    public $estado = 1;

    public function render()
    {
        // Traemos los municipios con su provincia
        $this->municipios = Municipio::with('provincia')->orderBy('nombre', 'asc')->get();
        // Menú desplegable solo con provincias activas
        $this->provincias = Provincia::where('estado', 1)->orderBy('nombre', 'asc')->get();

        return view('livewire.gestion-municipios');
    }

    public function guardar()
    {
        $this->validate([
            'provincia_id' => 'required|exists:provincias,id',
            'nombre' => 'required|string|max:255|unique:municipios,nombre'.($this->municipio_id ? ','.$this->municipio_id : ''),

            'estado' => 'nullable|boolean',
        ]);

        Municipio::updateOrCreate(
            ['id' => $this->municipio_id],
            [
                'provincia_id' => $this->provincia_id,
                'nombre' => $this->nombre,
                'estado' => $this->estado,
            ]
        );

        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $municipio = Municipio::findOrFail($id);
        $this->municipio_id = $municipio->id;
        $this->provincia_id = $municipio->provincia_id;
        $this->nombre = $municipio->nombre;
        $this->estado = $municipio->estado;
    }

    public function eliminar($id)
    {
        Municipio::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['municipio_id', 'provincia_id', 'nombre']);
        $this->estado = 1;
    }
}
