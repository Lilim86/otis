<?php

namespace App\Livewire;

use App\Models\Contrato;
use App\Models\Edificio;
use App\Models\Equipo;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DashboardPrincipal extends Component
{
    public $totalEdificios;

    public $totalEquipos;

    public $totalContratos;

    public $recaudacion;

    public $precioDolar = 'Cargando...';

    public function mount()
    {
        // 1. Contamos las estadísticas de tu Base de Datos
        $this->totalEdificios = Edificio::count();
        $this->totalEquipos = Equipo::count();
        $this->totalContratos = Contrato::count();
        $this->recaudacion = Contrato::sum('monto_total');

        // 2. ¡CONSUMO DE API EXTERNA EN TIEMPO REAL!
        try {
            // Llamamos a la API pública de ExchangeRate
            $response = Http::get('https://open.er-api.com/v6/latest/USD');

            if ($response->successful()) {
                $datos = $response->json();
                // Extraemos el valor exacto del Boliviano (BOB)
                $this->precioDolar = $datos['rates']['BOB'] ?? '6.96';
            } else {
                $this->precioDolar = 'Error de API';
            }
        } catch (\Exception $e) {
            $this->precioDolar = 'Sin conexión';
        }
    }

    public function render()
    {
        return view('livewire.dashboard-principal');
    }
}
