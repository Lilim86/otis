<?php

namespace App\Livewire;

use App\Models\Insumo;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionInsumos extends Component
{
    public $insumos;

    public $insumo_id;

    public $codigo;

    public $descripcion;

    public $tipo = 'HERRAMIENTA';

    public $stock_actual = 0;

    public $estado = 1;

    public function render()
    {
        $this->insumos = Insumo::orderBy('codigo', 'asc')->get();

        return view('livewire.gestion-insumos');
    }

    public function guardar()
    {
        $this->validate([
            'codigo' => 'required|string|unique:insumos,codigo'.($this->insumo_id ? ','.$this->insumo_id : ''),
            'descripcion' => 'required|string|max:255',
            'tipo' => 'required|string',
            'stock_actual' => 'required|numeric|min:0',

            'estado' => 'nullable|boolean',
        ]);

        Insumo::updateOrCreate(
            ['id' => $this->insumo_id],
            [
                'codigo' => $this->codigo,
                'descripcion' => $this->descripcion,
                'tipo' => $this->tipo,
                'stock_actual' => $this->stock_actual,
                'estado' => $this->estado,
            ]
        );
        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $insumo = Insumo::findOrFail($id);
        $this->insumo_id = $insumo->id;
        $this->codigo = $insumo->codigo;
        $this->descripcion = $insumo->descripcion;
        $this->tipo = $insumo->tipo;
        $this->stock_actual = $insumo->stock_actual;
        $this->estado = $insumo->estado;
    }

    public function eliminar($id)
    {
        Insumo::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['insumo_id', 'codigo', 'descripcion']);
        $this->tipo = 'HERRAMIENTA';
        $this->stock_actual = 0;
        $this->estado = 1;
    }
}
