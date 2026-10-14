<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Edificio;
use App\Models\Equipo;

class DashboardService
{
    public function __construct(private TipoCambioService $tipoCambio) {}

    public function resumen(): array
    {
        return [
            'total_edificios' => Edificio::count(),
            'total_equipos' => Equipo::count(),
            'total_contratos' => Contrato::count(),
            'monto_contratos' => (float) Contrato::sum('monto_total'),
            'dolar_bob' => $this->tipoCambio->bolivianosPorDolar(),
        ];
    }
}
