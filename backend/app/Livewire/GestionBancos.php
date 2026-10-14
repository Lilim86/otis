<?php

namespace App\Livewire;

use App\Models\Banco;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionBancos extends Component
{
    public $bancos;

    public $banco_id;

    public $nombre;

    public $sigla;

    public $estado = 1;

    public function render()
    {
        $this->bancos = Banco::orderBy('nombre', 'asc')->get();

        return view('livewire.gestion-bancos');
    }

    public function guardar()
    {
        $this->validate([
            'nombre' => 'required|string|max:255|unique:bancos,nombre'.($this->banco_id ? ','.$this->banco_id : ''),
            'sigla' => 'nullable|string|max:50',

            'estado' => 'nullable|boolean',
        ]);

        Banco::updateOrCreate(
            ['id' => $this->banco_id],
            ['nombre' => $this->nombre, 'sigla' => $this->sigla, 'estado' => $this->estado]
        );
        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $banco = Banco::findOrFail($id);
        $this->banco_id = $banco->id;
        $this->nombre = $banco->nombre;
        $this->sigla = $banco->sigla;
        $this->estado = $banco->estado;
    }

    public function eliminar($id)
    {
        Banco::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['banco_id', 'nombre', 'sigla']);
        $this->estado = 1;
    }
}
