<?php

namespace App\Livewire;

use App\Models\Municipio;
use App\Models\Zona;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionZonas extends Component
{
    public $zonas;

    public $municipios;

    public $zona_id;

    public $municipio_id;

    public $nombre;

    public $estado = 1;

    public function render()
    {
        $this->zonas = Zona::with('municipio')->orderBy('nombre', 'asc')->get();
        $this->municipios = Municipio::where('estado', 1)->orderBy('nombre', 'asc')->get();

        return view('livewire.gestion-zonas');
    }

    public function guardar()
    {
        $this->validate([
            'municipio_id' => 'required|exists:municipios,id',
            'nombre' => 'required|string|max:255|unique:zonas,nombre'.($this->zona_id ? ','.$this->zona_id : ''),

            'estado' => 'nullable|boolean',
        ]);

        Zona::updateOrCreate(
            ['id' => $this->zona_id],
            [
                'municipio_id' => $this->municipio_id,
                'nombre' => $this->nombre,
                'estado' => $this->estado,
            ]
        );

        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $zona = Zona::findOrFail($id);
        $this->zona_id = $zona->id;
        $this->municipio_id = $zona->municipio_id;
        $this->nombre = $zona->nombre;
        $this->estado = $zona->estado;
    }

    public function eliminar($id)
    {
        Zona::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['zona_id', 'municipio_id', 'nombre']);
        $this->estado = 1;
    }
}
