<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Edificio;
use App\Models\Empresa;
use App\Models\Equipo;
use App\Models\User;
use App\Models\VisitaMantenimiento;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function __construct(
        private TipoCambioService $tipoCambio,
        private InventarioService $inventario,
        private TableroTecnicosService $tablero,
    ) {}

    /** Cada bloque se entrega solo si el rol de quien consulta puede ver el módulo de donde sale. */
    public function resumen(?User $usuario = null): array
    {
        $resumen = [
            'total_edificios' => Edificio::count(),
            'total_equipos' => Equipo::count(),
            'total_contratos' => Contrato::count(),
            'monto_contratos' => (float) Contrato::sum('monto_total'),
            'dolar_bob' => $this->tipoCambio->bolivianosPorDolar(),
        ];

        if ($this->puede($usuario, 'empresas.ver') && $this->puede($usuario, 'contratos.ver')) {
            $resumen += ['clientes' => $this->clientes(), 'total_clientes' => Empresa::count()];
        }

        if ($this->puede($usuario, 'visitas-mantenimiento.ver')) {
            $t = $this->tablero->armar(today()->toDateString(), today()->addDays(7)->toDateString());
            $resumen += [
                'visitas' => $t['resumen'] + ['hoy' => collect($t['visitas'])->where('fecha', today()->toDateString())->count()],
                'tecnicos' => $t['tecnicos']->map(fn ($x) => collect($x)->except('proximas')->all())->values(),
            ];
        }

        if ($this->puede($usuario, 'insumos.ver')) {
            $resumen += ['stock_bajo' => $this->inventario->stockBajo()];
        }
        if ($this->puede($usuario, 'movimientos-almacen.ver')) {
            $resumen += ['herramientas_en_poder' => array_slice($this->inventario->herramientasEnPoder(), 0, 8)];
        }

        return $resumen;
    }

    private function puede(?User $usuario, string $permiso): bool
    {
        return ! config('sofia.permisos_activos') || ! $usuario || $usuario->id === 1 || $usuario->can($permiso);
    }

    /** Los clientes con más por cobrar primero: contratos, equipos, deuda y próxima visita de cada uno. */
    private function clientes(): array
    {
        $saldos = DB::table('cobranzas')->join('contratos', 'contratos.id', '=', 'cobranzas.contrato_id')
            ->whereNull('cobranzas.deleted_at')->where('cobranzas.estado_registro', '!=', 'ANL')
            ->groupBy('contratos.empresa_id')->selectRaw('contratos.empresa_id, SUM(cobranzas.monto_facturado - cobranzas.cobrado_bs) AS saldo')
            ->pluck('saldo', 'empresa_id');

        $proximas = VisitaMantenimiento::join('contratos', 'contratos.id', '=', 'visitas_mantenimiento.contrato_id')
            ->whereIn('visitas_mantenimiento.situacion', ['PROGRAMADA', 'REPROGRAMADA', 'SOLICITADA', 'EN_CURSO'])
            ->groupBy('contratos.empresa_id')->selectRaw('contratos.empresa_id, MIN(visitas_mantenimiento.fecha_programada) AS proxima')
            ->pluck('proxima', 'empresa_id');

        return Empresa::withCount(['contratos as contratos_vigentes' => fn ($q) => $q->where('fecha_fin', '>=', today()->toDateString())->where('estado_registro', '!=', 'ANL')])
            ->get(['id', 'denominacion'])
            ->map(fn (Empresa $e) => [
                'id' => $e->id, 'cliente' => $e->denominacion, 'contratos' => $e->contratos_vigentes,
                'equipos' => Equipo::whereIn('edificio_id', Contrato::where('empresa_id', $e->id)->select('edificio_id'))->count(),
                'por_cobrar_bs' => round((float) ($saldos[$e->id] ?? 0), 2),
                'proxima_visita' => isset($proximas[$e->id]) ? substr((string) $proximas[$e->id], 0, 10) : null,
            ])->sortByDesc('por_cobrar_bs')->take(10)->values()->all();
    }
}
