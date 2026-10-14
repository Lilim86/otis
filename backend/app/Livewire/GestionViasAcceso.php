<?php

namespace App\Livewire;

use App\Models\ViaAcceso;
use App\Models\Zona;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionViasAcceso extends Component
{
    public $vias;

    public $zonas;

    public $via_id;

    public $zona_id;

    public $nombre;

    public $estado = 1;

    public function render()
    {
        $this->vias = ViaAcceso::with('zona')->orderBy('nombre', 'asc')->get();
        $this->zonas = Zona::where('estado', 1)->orderBy('nombre', 'asc')->get();

        return view('livewire.gestion-vias-acceso');
    }

    public function guardar()
    {
        $this->validate([
            'zona_id' => 'required|exists:zonas,id',
            'nombre' => 'required|string|max:255|unique:via_accesos,nombre'.($this->via_id ? ','.$this->via_id : ''),

            'estado' => 'nullable|boolean',
        ]);

        ViaAcceso::updateOrCreate(
            ['id' => $this->via_id],
            [
                'zona_id' => $this->zona_id,
                'nombre' => $this->nombre,
                'estado' => $this->estado,
            ]
        );

        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $via = ViaAcceso::findOrFail($id);
        $this->via_id = $via->id;
        $this->zona_id = $via->zona_id;
        $this->nombre = $via->nombre;
        $this->estado = $via->estado;
    }

    public function eliminar($id)
    {
        ViaAcceso::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['via_id', 'zona_id', 'nombre']);
        $this->estado = 1;
    }
}
