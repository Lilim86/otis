<?php

namespace App\Livewire;

use App\Models\Departamento;
use App\Services\ReporteCombinadoService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class GestionReportes extends Component
{
    public string $clave = '';

    public $desde;

    public $hasta;

    public $departamento_id = '';

    public $q = '';

    private const VISTA_PREVIA = 100;

    public function render(ReporteCombinadoService $reportes)
    {
        $usuario = auth()->user();
        $disponibles = $reportes->disponibles($usuario);

        $reporte = null;
        if ($this->clave && $reportes->puede($usuario, $this->clave)) {
            $reporte = $reportes->generar($this->clave, array_filter([
                'desde' => $this->desde, 'hasta' => $this->hasta, 'departamento_id' => $this->departamento_id, 'q' => $this->q,
            ]));
        }

        return view('livewire.gestion-reportes', [
            'disponibles' => $disponibles,
            'reporte' => $reporte,
            'vistaPrevia' => self::VISTA_PREVIA,
            'departamentos' => Departamento::orderBy('nombre')->get(['id', 'nombre']),
            'consulta' => array_filter(['desde' => $this->desde, 'hasta' => $this->hasta, 'departamento_id' => $this->departamento_id, 'q' => $this->q]),
        ]);
    }
}
