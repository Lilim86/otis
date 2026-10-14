<?php

namespace App\Services;

use App\Models\Cobranza;
use App\Models\Contrato;
use App\Models\CuotaContrato;
use App\Models\Emergencia;
use App\Models\Insumo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reportes de seguimiento y cobranzas del SOFIA. Todos se basan en las cuotas de los contratos,
 * del período (gestión) elegido, y devuelven filas planas listas para tabla o CSV.
 */
class ReporteService
{
    public const SEGUIMIENTO = [
        'cuotas_por_facturar', 'facturas_por_cobrar', 'acumulado_regional', 'acumulado_cliente', 'mora_regional', 'programado_vs_ejecutado',
    ];

    public const COBRANZAS = ['cobrador', 'regional', 'cuenta_bancaria', 'cliente', 'mes'];

    public function seguimiento(string $tipo, int $gestion, ?int $regionalId): array
    {
        return match ($tipo) {
            'cuotas_por_facturar' => $this->cuotasPorFacturar($gestion, $regionalId),
            'facturas_por_cobrar' => $this->facturasPorCobrar($gestion, $regionalId),
            'acumulado_regional' => $this->acumulado($gestion, $regionalId, fn ($c) => $c->contrato?->regional?->nombre ?? 'SIN REGIONAL', 'regional'),
            'acumulado_cliente' => $this->acumulado($gestion, $regionalId, fn ($c) => $c->contrato?->empresa?->denominacion ?? 'SIN CLIENTE', 'cliente'),
            'mora_regional' => $this->moraPorRegional($gestion, $regionalId),
            'programado_vs_ejecutado' => $this->programadoVsEjecutado($gestion, $regionalId),
        };
    }

    public function cobranzas(string $agrupar, ?string $desde, ?string $hasta): array
    {
        $cobranzas = Cobranza::with(['contrato.empresa', 'contrato.regional', 'cuentaBancaria.banco', 'cobrador'])
            ->where('estado_registro', '!=', 'ANL')
            ->when($desde, fn ($q) => $q->whereDate('fecha_cobro', '>=', $desde))
            ->when($hasta, fn ($q) => $q->whereDate('fecha_cobro', '<=', $hasta))
            ->get();

        $clave = match ($agrupar) {
            'cobrador' => fn ($c) => $c->cobrador?->nombre_completo ?? 'SIN COBRADOR',
            'regional' => fn ($c) => $c->contrato?->regional?->nombre ?? 'SIN REGIONAL',
            'cuenta_bancaria' => fn ($c) => $c->cuentaBancaria ? "{$c->cuentaBancaria->banco?->nombre} {$c->cuentaBancaria->numero_cuenta}" : 'SIN CUENTA',
            'cliente' => fn ($c) => $c->contrato?->empresa?->denominacion ?? 'SIN CLIENTE',
            'mes' => fn ($c) => Carbon::parse($c->fecha_cobro)->format('Y-m'),
        };

        return $cobranzas->groupBy($clave)->map(fn (Collection $filas, $nombre) => [
            $agrupar => $nombre,
            'cantidad' => $filas->count(),
            'facturado_bs' => round($filas->sum('monto_facturado'), 2),
            'cobrado_bs' => round($filas->sum('cobrado_bs'), 2),
            'cobrado_usd' => round($filas->sum('cobrado_usd'), 2),
        ])->sortKeys()->values()->all();
    }

    /** @return array<string, array> */
    public function alertas(string $tipo, int $dias): array
    {
        return match ($tipo) {
            'contratos' => $this->alertaContratos($dias),
            'repuestos' => $this->alertaRepuestos(),
            'reparaciones' => $this->alertaReparaciones($dias),
        };
    }

    // ---------------------------------------------------------------- seguimiento

    private function cuotas(int $gestion, ?int $regionalId): Collection
    {
        return CuotaContrato::with(['contrato.empresa', 'contrato.edificio', 'contrato.regional'])
            ->whereYear('fecha_cuota', $gestion)
            ->whereHas('contrato', fn ($q) => $q->where('estado_registro', '!=', 'ANL')->when($regionalId, fn ($q) => $q->where('regional_id', $regionalId)))
            ->orderBy('fecha_cuota')
            ->get();
    }

    private function cuotasPorFacturar(int $gestion, ?int $regionalId): array
    {
        return $this->cuotas($gestion, $regionalId)
            ->where('estado_cuota', 'PENDIENTE')
            ->filter(fn ($c) => $c->fecha_cuota->lte(today()))
            ->map(fn ($c) => [
                'regional' => $c->contrato->regional?->nombre,
                'cliente' => $c->contrato->empresa?->denominacion,
                'edificio' => $c->contrato->edificio?->denominacion,
                'contrato' => $c->contrato->codigo_contrato ?? "#{$c->contrato_id}",
                'nro_cuota' => $c->nro_cuota,
                'fecha_cuota' => $c->fecha_cuota->toDateString(),
                'dias_mora' => (int) $c->fecha_cuota->diffInDays(today()),
                'monto_bs' => (float) $c->monto_bs,
            ])->values()->all();
    }

    private function facturasPorCobrar(int $gestion, ?int $regionalId): array
    {
        return Cobranza::with(['contrato.empresa', 'contrato.regional'])
            ->whereNotNull('nro_factura')->where('estado_registro', '!=', 'ANL')
            ->whereYear('fecha_cobro', $gestion)
            ->whereHas('contrato', fn ($q) => $q->when($regionalId, fn ($q) => $q->where('regional_id', $regionalId)))
            ->get()
            ->filter(fn ($c) => (float) $c->cobrado_bs < (float) $c->monto_facturado)
            ->map(fn ($c) => [
                'regional' => $c->contrato->regional?->nombre,
                'cliente' => $c->contrato->empresa?->denominacion,
                'nro_factura' => $c->nro_factura,
                'fecha_facturacion' => $c->fecha_facturacion ? Carbon::parse($c->fecha_facturacion)->toDateString() : null,
                'monto_facturado_bs' => (float) $c->monto_facturado,
                'cobrado_bs' => (float) $c->cobrado_bs,
                'saldo_bs' => round($c->monto_facturado - $c->cobrado_bs, 2),
            ])->values()->all();
    }

    /** Acumulado en Bs por regional o cliente: lo programado, lo facturado y lo cobrado. */
    private function acumulado(int $gestion, ?int $regionalId, callable $clave, string $nombre): array
    {
        return $this->cuotas($gestion, $regionalId)->groupBy($clave)->map(function (Collection $cuotas, $grupo) use ($nombre) {
            $programado = (float) $cuotas->sum('monto_bs');

            return [
                $nombre => $grupo,
                'cuotas' => $cuotas->count(),
                'programado_bs' => round($programado, 2),
                'facturado_bs' => round($cuotas->whereIn('estado_cuota', ['FACTURADA', 'COBRADA'])->sum('monto_bs'), 2),
                'cobrado_bs' => round($cuotas->where('estado_cuota', 'COBRADA')->sum('monto_bs'), 2),
                'pendiente_bs' => round($cuotas->where('estado_cuota', '!=', 'COBRADA')->sum('monto_bs'), 2),
            ];
        })->sortKeys()->values()->all();
    }

    /** Facturado sin cobrar (mora), por regional. */
    private function moraPorRegional(int $gestion, ?int $regionalId): array
    {
        return collect($this->facturasPorCobrar($gestion, $regionalId))->groupBy(fn ($f) => $f['regional'] ?? 'SIN REGIONAL')
            ->map(fn (Collection $filas, $regional) => [
                'regional' => $regional,
                'facturas' => $filas->count(),
                'saldo_bs' => round($filas->sum('saldo_bs'), 2),
            ])->sortKeys()->values()->all();
    }

    private function programadoVsEjecutado(int $gestion, ?int $regionalId): array
    {
        $porMes = $this->cuotas($gestion, $regionalId)->groupBy(fn ($c) => (int) $c->fecha_cuota->format('n'));

        return collect(range(1, 12))->map(function ($mes) use ($porMes, $gestion) {
            $cuotas = $porMes->get($mes, collect());
            $programado = (float) $cuotas->sum('monto_bs');
            $ejecutado = (float) $cuotas->whereIn('estado_cuota', ['FACTURADA', 'COBRADA'])->sum('monto_bs');

            return [
                'mes' => sprintf('%d-%02d', $gestion, $mes),
                'programado_bs' => round($programado, 2),
                'ejecutado_bs' => round($ejecutado, 2),
                'avance_pct' => $programado > 0 ? round($ejecutado / $programado * 100, 1) : null,
            ];
        })->all();
    }

    // -------------------------------------------------------------------- alertas

    /** Contratos vencidos o por vencer dentro de $dias días. */
    private function alertaContratos(int $dias): array
    {
        return Contrato::with(['empresa', 'edificio'])
            ->where('estado_registro', '!=', 'ANL')
            ->whereDate('fecha_fin', '<=', today()->addDays($dias))
            ->orderBy('fecha_fin')->get()
            ->map(fn ($c) => [
                'contrato' => $c->codigo_contrato ?? "#{$c->id}",
                'cliente' => $c->empresa?->denominacion,
                'edificio' => $c->edificio?->denominacion,
                'fecha_fin' => Carbon::parse($c->fecha_fin)->toDateString(),
                'situacion' => Carbon::parse($c->fecha_fin)->lt(today()) ? 'VENCIDO' : 'POR VENCER',
                'dias' => (int) today()->diffInDays(Carbon::parse($c->fecha_fin), false),
            ])->all();
    }

    /** Bienes con stock igual o por debajo del mínimo definido. */
    private function alertaRepuestos(): array
    {
        return Insumo::where('estado_registro', '!=', 'ANL')->where('stock_minimo', '>', 0)
            ->whereColumn('stock_actual', '<=', 'stock_minimo')->orderBy('codigo')->get()
            ->map(fn ($i) => [
                'codigo' => $i->codigo,
                'descripcion' => $i->descripcion,
                'rotacion' => $i->rotacion,
                'stock_actual' => (float) $i->stock_actual,
                'stock_minimo' => (float) $i->stock_minimo,
                'faltante' => round($i->stock_minimo - $i->stock_actual, 2),
            ])->all();
    }

    /** Reparaciones (emergencias) sin solucionar con más de $dias días. */
    private function alertaReparaciones(int $dias): array
    {
        return Emergencia::with('ascensor.zona')->where('situacion', '!=', 'Solucionado')
            ->where('created_at', '<=', now()->subDays($dias))->orderBy('created_at')->get()
            ->map(fn ($e) => [
                'ascensor' => $e->ascensor?->codigo,
                'edificio' => $e->ascensor?->cliente_edificio,
                'zona' => $e->ascensor?->zona?->nombre,
                'gravedad' => $e->gravedad,
                'situacion' => $e->situacion,
                'reportada' => $e->created_at->toDateString(),
                'dias_abierta' => (int) $e->created_at->diffInDays(now()),
            ])->all();
    }
}
