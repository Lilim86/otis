<?php

namespace App\Http\Controllers;

use App\Services\ReporteCombinadoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Reportes combinados de tablas, en pantalla (JSON), CSV y PDF. Sirve a la API y a las pantallas web. */
class ReporteCombinadoController extends Controller
{
    public function __construct(private ReporteCombinadoService $reportes) {}

    public function catalogo(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportes->disponibles($request->user())]);
    }

    public function ver(Request $request, string $clave, ?string $formato = null): Response
    {
        $formato ??= $request->query('formato', 'json');
        abort_unless(in_array($formato, ['json', 'csv', 'pdf'], true), 422, 'Formato no válido.');
        abort_unless(isset(ReporteCombinadoService::CATALOGO[$clave]), 404, 'Reporte no encontrado.');
        abort_unless($this->reportes->puede($request->user(), $clave), 403, 'No tienes acceso a este reporte.');

        $reporte = $this->reportes->generar($clave, $this->filtros($request));

        return match ($formato) {
            'csv' => $this->csv($reporte),
            'pdf' => $this->pdf($reporte, $request),
            default => response()->json(['data' => $reporte]),
        };
    }

    /** Kardex del SOFIA (REP-0069), ahora combinado con grupo, almacén, regional y país. */
    public function kardex(Request $request): Response
    {
        return $this->ver($request, 'kardex', 'pdf');
    }

    public function filtros(Request $request): array
    {
        return $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'departamento_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
    }

    private function pdf(array $reporte, Request $request): Response
    {
        $ancho = count($reporte['columnas']);

        return Pdf::loadView('reportes.combinado', [
            'reporte' => $reporte, 'filtros' => $this->filtros($request), 'emitidoPor' => $request->user()->name ?: $request->user()->username,
        ])->setPaper($ancho > 12 ? 'a3' : 'letter', 'landscape')->stream("{$reporte['clave']}_".today()->format('Ymd').'.pdf');
    }

    private function csv(array $reporte): Response
    {
        return response()->streamDownload(function () use ($reporte) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF"); // BOM para que Excel lea bien las tildes
            fputcsv($salida, array_values($reporte['columnas']), ';');
            foreach ($reporte['filas'] as $fila) {
                fputcsv($salida, array_map(fn ($clave) => $this->celda($fila[$clave] ?? null), array_keys($reporte['columnas'])), ';');
            }
            fclose($salida);
        }, "{$reporte['clave']}_".today()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Evita la inyección de fórmulas en Excel: un texto que empieza con = + - @ se neutraliza. */
    private function celda(mixed $valor): mixed
    {
        return is_string($valor) && $valor !== '' && str_contains('=+-@', $valor[0]) ? "'{$valor}" : $valor;
    }
}
