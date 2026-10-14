<?php

namespace App\Livewire;

use App\Models\OrdenMantenimiento;
use App\Models\Repuesto;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionMantenimiento extends Component
{
    public $ordenes;

    public $lista_repuestos;

    public $orden_id;

    public $codigo_orden;

    public $equipo_ascensor;

    public $fecha_programada;

    public $situacion = 'Pendiente';

    public $descripcion_trabajo;

    public $repuesto_id;

    public $cantidad_utilizada;

    public function render()
    {
        // Traemos las órdenes y los repuestos que tengan stock disponible
        $this->ordenes = OrdenMantenimiento::with('repuesto')->orderBy('fecha_programada', 'desc')->get();
        $this->lista_repuestos = Repuesto::where('stock', '>', 0)->get();

        return view('livewire.gestion-mantenimiento');
    }

    public function guardar()
    {
        $this->validate([
            'codigo_orden' => 'required|string|unique:orden_mantenimientos,codigo_orden,'.$this->orden_id,
            'equipo_ascensor' => 'required|string',
            'fecha_programada' => 'required|date',
            'descripcion_trabajo' => 'required|string',

            'cantidad_utilizada' => 'nullable|numeric|min:0',
            'repuesto_id' => 'nullable|exists:repuestos,id',
            'situacion' => 'required|in:Pendiente,En Proceso,Finalizado',
        ]);

        OrdenMantenimiento::updateOrCreate(
            ['id' => $this->orden_id],
            [
                'codigo_orden' => $this->codigo_orden,
                'equipo_ascensor' => $this->equipo_ascensor,
                'fecha_programada' => $this->fecha_programada,
                'situacion' => $this->situacion,
                'descripcion_trabajo' => $this->descripcion_trabajo,
                'repuesto_id' => $this->repuesto_id ?: null,
                'cantidad_utilizada' => $this->cantidad_utilizada ?: 0,
            ]
        );

        $this->limpiarCampos();
    }

    public function finalizarOrden($id)
    {
        $orden = OrdenMantenimiento::findOrFail($id);

        if ($orden->situacion !== 'Finalizado') {
            // Lógica inteligente: Descontar del inventario
            if ($orden->repuesto_id && $orden->cantidad_utilizada > 0) {
                $repuesto = Repuesto::find($orden->repuesto_id);
                if ($repuesto && $repuesto->stock >= $orden->cantidad_utilizada) {
                    $repuesto->stock -= $orden->cantidad_utilizada;
                    $repuesto->save();
                }
            }

            $orden->situacion = 'Finalizado';
            $orden->save();
        }
    }

    public function eliminar($id)
    {
        OrdenMantenimiento::findOrFail($id)->delete();
    }

    public function limpiarCampos()
    {
        $this->reset(['orden_id', 'codigo_orden', 'equipo_ascensor', 'fecha_programada', 'descripcion_trabajo', 'repuesto_id', 'cantidad_utilizada']);
        $this->situacion = 'Pendiente';
    }
}
