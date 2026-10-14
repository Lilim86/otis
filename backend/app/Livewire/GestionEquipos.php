<?php

namespace App\Livewire;

use App\Models\Edificio;
use App\Models\Equipo;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionEquipos extends Component
{
    public $equipos;

    public $edificios;

    public $equipo_id;

    public $codigo;

    public $descripcion;

    public $edificio_id;

    public $industria;

    public $marca;

    public $modelo;

    public $paradas;

    public $estado = 1;

    public function render()
    {
        $this->equipos = Equipo::with('edificio')->orderBy('codigo', 'asc')->get();
        $this->edificios = Edificio::where('estado', 1)->orderBy('denominacion', 'asc')->get();

        return view('livewire.gestion-equipos');
    }

    public function guardar()
    {
        $this->validate([
            'codigo' => 'required|string|unique:equipos,codigo'.($this->equipo_id ? ','.$this->equipo_id : ''),
            'descripcion' => 'required|string|max:255',
            'edificio_id' => 'nullable|exists:edificios,id',
            'paradas' => 'nullable|integer|min:1',

            'estado' => 'nullable|boolean',
            'industria' => 'nullable|string|max:100',
            'marca' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
        ]);

        Equipo::updateOrCreate(
            ['id' => $this->equipo_id],
            [
                'codigo' => $this->codigo,
                'descripcion' => $this->descripcion,
                'edificio_id' => $this->edificio_id,
                'industria' => $this->industria,
                'marca' => $this->marca,
                'modelo' => $this->modelo,
                'paradas' => $this->paradas,
                'estado' => $this->estado,
            ]
        );
        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $equipo = Equipo::findOrFail($id);
        $this->equipo_id = $equipo->id;
        $this->codigo = $equipo->codigo;
        $this->descripcion = $equipo->descripcion;
        $this->edificio_id = $equipo->edificio_id;
        $this->industria = $equipo->industria;
        $this->marca = $equipo->marca;
        $this->modelo = $equipo->modelo;
        $this->paradas = $equipo->paradas;
        $this->estado = $equipo->estado;
    }

    public function eliminar($id)
    {
        Equipo::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['equipo_id', 'codigo', 'descripcion', 'edificio_id', 'industria', 'marca', 'modelo', 'paradas']);
        $this->estado = 1;
    }
}
