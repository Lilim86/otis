<?php

namespace App\Livewire;

use App\Models\Contrato;
use App\Models\Edificio;
use App\Models\Empresa;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionContratos extends Component
{
    public $contratos;

    public $empresas;

    public $edificios;

    public $contrato_id;

    public $empresa_id;

    public $edificio_id;

    public $fecha_inicio;

    public $fecha_fin;

    public $monto_total;

    public $estado = 1;

    public function render()
    {
        $this->contratos = Contrato::with(['empresa', 'edificio'])->orderBy('fecha_inicio', 'desc')->get();
        $this->empresas = Empresa::where('estado', 1)->orderBy('denominacion', 'asc')->get();
        $this->edificios = Edificio::where('estado', 1)->orderBy('denominacion', 'asc')->get();

        return view('livewire.gestion-contratos');
    }

    public function guardar()
    {
        $this->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'edificio_id' => 'required|exists:edificios,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'monto_total' => 'required|numeric|min:0',

            'estado' => 'nullable|boolean',
        ]);

        Contrato::updateOrCreate(
            ['id' => $this->contrato_id],
            [
                'empresa_id' => $this->empresa_id,
                'edificio_id' => $this->edificio_id,
                'fecha_inicio' => $this->fecha_inicio,
                'fecha_fin' => $this->fecha_fin,
                'monto_total' => $this->monto_total,
                'estado' => $this->estado,
            ]
        );
        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $contrato = Contrato::findOrFail($id);
        $this->contrato_id = $contrato->id;
        $this->empresa_id = $contrato->empresa_id;
        $this->edificio_id = $contrato->edificio_id;
        $this->fecha_inicio = $contrato->fecha_inicio;
        $this->fecha_fin = $contrato->fecha_fin;
        $this->monto_total = $contrato->monto_total;
        $this->estado = $contrato->estado;
    }

    public function eliminar($id)
    {
        Contrato::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['contrato_id', 'empresa_id', 'edificio_id', 'fecha_inicio', 'fecha_fin', 'monto_total']);
        $this->estado = 1;
    }
}
