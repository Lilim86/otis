<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Ingenieria\CobranzaAnaliticaService;
use App\Services\Ingenieria\ConfiabilidadService;
use App\Services\Ingenieria\PrioridadFallasService;
use App\Services\Ingenieria\RutasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Aportes de ingeniería: rutas óptimas, asignación por cercanía, confiabilidad, nivel de servicio y cobranza. */
class IngenieriaController extends Controller
{
    public function __construct(private RutasService $rutas, private ConfiabilidadService $confiabilidad) {}

    private function periodo(Request $request, int $atras = 90, int $adelante = 0): array
    {
        $f = $request->validate(['desde' => ['nullable', 'date'], 'hasta' => ['nullable', 'date', 'after_or_equal:desde']]);

        return [$f['desde'] ?? today()->subDays($atras)->toDateString(), $f['hasta'] ?? today()->addDays($adelante)->toDateString()];
    }

    /** Panel de impacto: lo más importante de cada aporte en una sola respuesta. */
    public function resumen(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->periodo($request);
        $hoy = $this->rutas->planificar(today()->toDateString());

        return response()->json(['data' => [
            'rutas_hoy' => $hoy['total'],
            'rutas_acumulado' => $this->rutas->historial(),
            'asignacion' => $this->rutas->compararAsignacion(today()->toDateString(), today()->addDays(14)->toDateString()),
            'confiabilidad' => $this->confiabilidad->analizar()['resumen'],
            'servicio' => $this->confiabilidad->nivelServicio($desde, $hasta),
        ]]);
    }

    public function rutas(Request $request): JsonResponse
    {
        $f = $request->validate(['fecha' => ['nullable', 'date'], 'tecnico_id' => ['nullable', 'integer']]);

        return response()->json(['data' => $this->rutas->planificar($f['fecha'] ?? today()->toDateString(), $f['tecnico_id'] ?? null)]);
    }

    public function aplicarRutas(Request $request): JsonResponse
    {
        $f = $request->validate(['fecha' => ['required', 'date']]);
        $plan = $this->rutas->aplicar($f['fecha'], $request->user()->id);

        return response()->json(['data' => $plan, 'message' => "Rutas guardadas: los técnicos ya ven el orden óptimo. Ahorro: {$plan['total']['ahorro_km']} km."]);
    }

    public function compararAsignacion(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->periodo($request, 0, 14);

        return response()->json(['data' => $this->rutas->compararAsignacion($desde, $hasta)]);
    }

    public function asignar(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->periodo($request, 0, 14);
        $r = $this->rutas->asignarPorCercania($desde, $hasta);

        return response()->json(['data' => $r, 'message' => "Se asignaron {$r['asignadas']} visita(s) por cercanía".($r['sin_cupo'] ? "; {$r['sin_cupo']} sin cupo." : '.')]);
    }

    public function confiabilidad(): JsonResponse
    {
        return response()->json(['data' => $this->confiabilidad->analizar()]);
    }

    public function adelantar(int $edificio): JsonResponse
    {
        $visita = $this->confiabilidad->adelantar($edificio);

        return response()->json(['data' => ['id' => $visita->id, 'fecha_programada' => $visita->fecha_programada->toDateString()], 'message' => 'Preventiva adelantada al '.$visita->fecha_programada->format('d/m/Y').'.']);
    }

    public function servicio(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->periodo($request);

        return response()->json(['data' => $this->confiabilidad->nivelServicio($desde, $hasta)]);
    }

    /** Prueba del clasificador de fallas: escribe una descripción y ve la prioridad que le daría. */
    public function clasificar(Request $request, PrioridadFallasService $triage): JsonResponse
    {
        $d = $request->validate(['descripcion' => ['required', 'string', 'max:1000'], 'urgente' => ['nullable', 'boolean']]);

        return response()->json(['data' => $triage->clasificar($d['descripcion'], (bool) ($d['urgente'] ?? false))]);
    }

    public function cobranza(CobranzaAnaliticaService $analitica): JsonResponse
    {
        return response()->json(['data' => $analitica->analizar()]);
    }
}
