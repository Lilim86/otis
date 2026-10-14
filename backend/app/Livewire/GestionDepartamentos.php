<?php

namespace App\Livewire;

use App\Models\Departamento;
use App\Models\Pais;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionDepartamentos extends Component
{
    public $departamentos;

    public $paises;

    public $departamento_id;

    public $pais_id;

    public $nombre;

    public $estado = 1; // 1 = Activo, 0 = Inactivo

    public function render()
    {
        // Traemos los departamentos junto con el nombre de su país (usando la relación)
        $this->departamentos = Departamento::with('pais')->orderBy('nombre', 'asc')->get();
        // Traemos solo los países activos para el menú desplegable
        $this->paises = Pais::where('estado', 1)->orderBy('nombre', 'asc')->get();

        return view('livewire.gestion-departamentos');
    }

    public function guardar()
    {
        $this->validate([
            'pais_id' => 'required|exists:paises,id',
            'nombre' => 'required|string|max:255|unique:departamentos,nombre'.($this->departamento_id ? ','.$this->departamento_id : ''),

            'estado' => 'nullable|boolean',
        ]);

        Departamento::updateOrCreate(
            ['id' => $this->departamento_id],
            [
                'pais_id' => $this->pais_id,
                'nombre' => $this->nombre,
                'estado' => $this->estado,
            ]
        );

        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $departamento = Departamento::findOrFail($id);
        $this->departamento_id = $departamento->id;
        $this->pais_id = $departamento->pais_id;
        $this->nombre = $departamento->nombre;
        $this->estado = $departamento->estado;
    }

    public function eliminar($id)
    {
        Departamento::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['departamento_id', 'pais_id', 'nombre']);
        $this->estado = 1;
    }
}
