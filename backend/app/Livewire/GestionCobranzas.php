<?php

namespace App\Livewire;

use App\Models\Cobranza;
use App\Models\Contrato;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionCobranzas extends Component
{
    public $cobranzas;

    public $contratos;

    public $cobranza_id;

    public $contrato_id;

    public $concepto;

    public $monto_facturado;

    public $fecha_cobro;

    public $nro_factura;

    public $estado = 0;

    public function render()
    {
        $this->cobranzas = Cobranza::with(['contrato.empresa'])->orderBy('fecha_cobro', 'desc')->get();
        $this->contratos = Contrato::with('empresa')->where('estado', 1)->get();

        return view('livewire.gestion-cobranzas');
    }

    public function guardar()
    {
        $this->validate([
            'contrato_id' => 'required|exists:contratos,id',
            'concepto' => 'required|string|max:255',
            'monto_facturado' => 'required|numeric|min:0',
            'fecha_cobro' => 'required|date',

            'estado' => 'nullable|boolean',
            'nro_factura' => 'nullable|string|max:100',
        ]);

        Cobranza::updateOrCreate(
            ['id' => $this->cobranza_id],
            [
                'contrato_id' => $this->contrato_id,
                'concepto' => $this->concepto,
                'monto_facturado' => $this->monto_facturado,
                'fecha_cobro' => $this->fecha_cobro,
                'nro_factura' => $this->nro_factura,
                'estado' => $this->estado,
            ]
        );
        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $cobro = Cobranza::findOrFail($id);
        $this->cobranza_id = $cobro->id;
        $this->contrato_id = $cobro->contrato_id;
        $this->concepto = $cobro->concepto;
        $this->monto_facturado = $cobro->monto_facturado;
        $this->fecha_cobro = $cobro->fecha_cobro;
        $this->nro_factura = $cobro->nro_factura;
        $this->estado = $cobro->estado;
    }

    public function eliminar($id)
    {
        Cobranza::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['cobranza_id', 'contrato_id', 'concepto', 'monto_facturado', 'fecha_cobro', 'nro_factura']);
        $this->estado = 0;
    }
}
