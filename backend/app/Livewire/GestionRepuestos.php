<?php

namespace App\Livewire;

use App\Models\Repuesto;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionRepuestos extends Component
{
    public $repuestos;

    public $codigo_parte;

    public $nombre;

    public $stock;

    public $precio;

    public $ubicacion_almacen;

    public $repuesto_id;

    public function render()
    {
        $this->repuestos = Repuesto::orderBy('id', 'desc')->get();

        return view('livewire.gestion-repuestos');
    }

    public function guardar()
    {
        $this->validate([
            'codigo_parte' => 'required|string|unique:repuestos,codigo_parte,'.$this->repuesto_id,
            'nombre' => 'required|string',
            'stock' => 'required|integer',
            'precio' => 'required|numeric',
            'ubicacion_almacen' => 'required|string',
        ]);

        Repuesto::updateOrCreate(
            ['id' => $this->repuesto_id],
            [
                'codigo_parte' => $this->codigo_parte,
                'nombre' => $this->nombre,
                'stock' => $this->stock,
                'precio' => $this->precio,
                'ubicacion_almacen' => $this->ubicacion_almacen,
            ]
        );

        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $repuesto = Repuesto::findOrFail($id);
        $this->repuesto_id = $id;
        $this->codigo_parte = $repuesto->codigo_parte;
        $this->nombre = $repuesto->nombre;
        $this->stock = $repuesto->stock;
        $this->precio = $repuesto->precio;
        $this->ubicacion_almacen = $repuesto->ubicacion_almacen;
    }

    public function eliminar($id)
    {
        Repuesto::findOrFail($id)->delete();
        $this->repuestos = Repuesto::orderBy('id', 'desc')->get(); // Refrescar lista
    }

    public function limpiarCampos()
    {
        $this->reset(['codigo_parte', 'nombre', 'stock', 'precio', 'ubicacion_almacen', 'repuesto_id']);
    }
}
