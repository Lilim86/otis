<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventarioService;
use App\Services\ReporteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reportes y alertas. Con ?formato=csv se descargan para abrir en Excel. */
class InformeController extends Controller
{
    public function __construct(private ReporteService $reportes) {}

    public function seguimiento(Request $request): JsonResponse|StreamedResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(ReporteService::SEGUIMIENTO)],
            'gestion' => ['nullable', 'integer', 'between:2000,2100'],
            'regional_id' => ['nullable', 'integer', Rule::exists('departamentos', 'id')],
            'formato' => ['nullable', 'in:json,csv'],
        ]);

        $filas = $this->reportes->seguimiento($datos['tipo'], (int) ($datos['gestion'] ?? today()->year), $datos['regional_id'] ?? null);

        return $this->responder($filas, "seguimiento_{$datos['tipo']}", $datos['formato'] ?? 'json');
    }

    public function cobranzas(Request $request): JsonResponse|StreamedResponse
    {
        $datos = $request->validate([
            'agrupar_por' => ['required', Rule::in(ReporteService::COBRANZAS)],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'formato' => ['nullable', 'in:json,csv'],
        ]);

        $filas = $this->reportes->cobranzas($datos['agrupar_por'], $datos['desde'] ?? null, $datos['hasta'] ?? null);

        return $this->responder($filas, "cobranzas_por_{$datos['agrupar_por']}", $datos['formato'] ?? 'json');
    }

    public function alertas(Request $request): JsonResponse|StreamedResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(['contratos', 'repuestos', 'reparaciones'])],
            'dias' => ['nullable', 'integer', 'between:0,365'],
            'formato' => ['nullable', 'in:json,csv'],
        ]);

        $filas = $this->reportes->alertas($datos['tipo'], (int) ($datos['dias'] ?? 30));

        return $this->responder($filas, "alertas_{$datos['tipo']}", $datos['formato'] ?? 'json');
    }

    /** Inventario valorado de todos los almacenes, listo para Excel. */
    public function inventario(Request $request, InventarioService $inventario): JsonResponse|StreamedResponse
    {
        $datos = $request->validate([
            'almacen_id' => ['required', Rule::exists('almacenes', 'id')->whereNull('deleted_at')],
            'formato' => ['nullable', 'in:json,csv'],
        ]);

        return $this->responder($inventario->inventario((int) $datos['almacen_id']), 'inventario_almacen', $datos['formato'] ?? 'json');
    }

    private function responder(array $filas, string $nombre, string $formato): JsonResponse|StreamedResponse
    {
        if ($formato !== 'csv') {
            return response()->json(['data' => $filas, 'total' => count($filas)]);
        }

        return response()->streamDownload(function () use ($filas) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF"); // BOM para que Excel lea bien las tildes
            if ($filas) {
                fputcsv($salida, array_keys($filas[0]), ';');
                foreach ($filas as $fila) {
                    fputcsv($salida, array_map(fn ($v) => $this->celda($v), $fila), ';');
                }
            }
            fclose($salida);
        }, "{$nombre}_".today()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Evita la inyección de fórmulas en Excel: un texto que empieza con = + - @ se neutraliza. */
    private function celda(mixed $valor): mixed
    {
        return is_string($valor) && $valor !== '' && str_contains('=+-@', $valor[0]) ? "'{$valor}" : $valor;
    }
}
