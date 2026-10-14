<?php

namespace App\Livewire;

use App\Models\Banco;
use App\Models\CuentaBancaria;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionCuentasBancarias extends Component
{
    public $cuentas;

    public $bancos;

    public $cuenta_id;

    public $banco_id;

    public $numero_cuenta;

    public $moneda = 'BOB';

    public $estado = 1;

    public function render()
    {
        $this->cuentas = CuentaBancaria::with('banco')->orderBy('banco_id', 'asc')->get();
        $this->bancos = Banco::where('estado', 1)->orderBy('nombre', 'asc')->get();

        return view('livewire.gestion-cuentas-bancarias');
    }

    public function guardar()
    {
        $this->validate([
            'banco_id' => 'required|exists:bancos,id',
            'numero_cuenta' => 'required|string|max:255|unique:cuentas_bancarias,numero_cuenta'.($this->cuenta_id ? ','.$this->cuenta_id : ''),
            'moneda' => 'required|string|in:BOB,USD',

            'estado' => 'nullable|boolean',
        ]);

        CuentaBancaria::updateOrCreate(
            ['id' => $this->cuenta_id],
            ['banco_id' => $this->banco_id, 'numero_cuenta' => $this->numero_cuenta, 'moneda' => $this->moneda, 'estado' => $this->estado]
        );
        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $cuenta = CuentaBancaria::findOrFail($id);
        $this->cuenta_id = $cuenta->id;
        $this->banco_id = $cuenta->banco_id;
        $this->numero_cuenta = $cuenta->numero_cuenta;
        $this->moneda = $cuenta->moneda;
        $this->estado = $cuenta->estado;
    }

    public function eliminar($id)
    {
        CuentaBancaria::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['cuenta_id', 'banco_id', 'numero_cuenta']);
        $this->moneda = 'BOB';
        $this->estado = 1;
    }
}
