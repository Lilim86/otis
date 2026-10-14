<?php

namespace App\Livewire;

use App\Models\Pais;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionPaises extends Component
{
    public $paises;

    public $pais_id;

    public $nombre;

    public $codigo_iso;

    public $estado = 1; // 1 = Activo, 0 = Inactivo

    public function render()
    {
        $this->paises = Pais::orderBy('nombre', 'asc')->get();

        return view('livewire.gestion-paises');
    }

    public function guardar()
    {
        // Aquí está la magia: si hay ID lo ignora (para editar), si no hay ID, hace una validación normal (para crear)
        $this->validate([
            'nombre' => 'required|string|max:255|unique:paises,nombre'.($this->pais_id ? ','.$this->pais_id : ''),
            'codigo_iso' => 'nullable|string|max:10',

            'estado' => 'nullable|boolean',
        ]);

        Pais::updateOrCreate(
            ['id' => $this->pais_id],
            [
                'nombre' => $this->nombre,
                'codigo_iso' => strtoupper($this->codigo_iso),
                'estado' => $this->estado,
            ]
        );

        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $pais = Pais::findOrFail($id);
        $this->pais_id = $pais->id;
        $this->nombre = $pais->nombre;
        $this->codigo_iso = $pais->codigo_iso;
        $this->estado = $pais->estado;
    }

    public function eliminar($id)
    {
        Pais::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['pais_id', 'nombre', 'codigo_iso']);
        $this->estado = 1;
    }
}
