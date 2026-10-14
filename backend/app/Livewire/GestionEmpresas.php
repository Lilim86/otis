<?php

namespace App\Livewire;

use App\Models\Empresa;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionEmpresas extends Component
{
    public $empresas;

    public $empresa_id;

    public $denominacion;

    public $nit;

    public $tipo_empresa = 'EMPRESA - CLIENTE';

    public $sigla;

    public $estado = 1;

    public function render()
    {
        $this->empresas = Empresa::orderBy('denominacion', 'asc')->get();

        return view('livewire.gestion-empresas');
    }

    public function guardar()
    {
        $this->validate([
            'denominacion' => 'required|string|max:255',
            'nit' => 'required|string|max:50',
            'tipo_empresa' => 'required|string|max:100',

            'estado' => 'nullable|boolean',
            'sigla' => 'nullable|string|max:50',
        ]);

        Empresa::updateOrCreate(
            ['id' => $this->empresa_id],
            ['denominacion' => $this->denominacion, 'nit' => $this->nit, 'tipo_empresa' => $this->tipo_empresa, 'sigla' => $this->sigla, 'estado' => $this->estado]
        );
        $this->limpiarCampos();
    }

    public function editar($id)
    {
        $emp = Empresa::findOrFail($id);
        $this->empresa_id = $emp->id;
        $this->denominacion = $emp->denominacion;
        $this->nit = $emp->nit;
        $this->tipo_empresa = $emp->tipo_empresa;
        $this->sigla = $emp->sigla;
        $this->estado = $emp->estado;
    }

    public function eliminar($id)
    {
        Empresa::findOrFail($id)->delete();
        $this->limpiarCampos();
    }

    public function limpiarCampos()
    {
        $this->reset(['empresa_id', 'denominacion', 'nit', 'sigla']);
        $this->tipo_empresa = 'EMPRESA - CLIENTE';
        $this->estado = 1;
    }
}
