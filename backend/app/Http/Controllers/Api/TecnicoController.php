<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VisitaMantenimiento;
use App\Services\NotificacionesClienteService;
use App\Services\UbicacionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Vista del técnico: sus visitas del día y de los próximos días, y cerrar una con su informe. */
class TecnicoController extends Controller
{
    private const ABIERTAS = ['PROGRAMADA', 'REPROGRAMADA', 'SOLICITADA'];

    public function ruta(Request $request): JsonResponse
    {
        $f = $request->validate(['hasta' => ['nullable', 'date'], 'realizadas' => ['nullable', 'boolean']]);
        $hasta = $f['hasta'] ?? today()->addDays(14)->toDateString();
        $ubicacion = new UbicacionResolver;

        $consulta = VisitaMantenimiento::with(['contrato.empresa', 'contrato.edificio.equipos', 'contrato.edificio.contactoTecnico'])
            ->where('tecnico_id', $request->user()->personal_id)->whereDate('fecha_programada', '<=', $hasta);

        // Por defecto: lo pendiente (incluye lo atrasado). Con "realizadas": también el último mes cerrado.
        $f['realizadas'] ?? false
            ? $consulta->whereDate('fecha_programada', '>=', today()->subDays(30))
            : $consulta->whereIn('situacion', self::ABIERTAS);

        $visitas = $consulta->orderBy('fecha_programada')->get();

        return response()->json(['data' => $visitas->map(function (VisitaMantenimiento $v) use ($ubicacion) {
            $edificio = $v->contrato->edificio;
            $u = $ubicacion->de($edificio);
            $contacto = $edificio?->contactoTecnico;

            return [
                'id' => $v->id, 'tipo' => $v->tipo, 'nro_visita' => $v->nro_visita, 'situacion' => $v->situacion,
                'fecha_programada' => $v->fecha_programada->toDateString(), 'fecha_realizada' => $v->fecha_realizada?->toDateString(),
                'dias' => (int) today()->diffInDays($v->fecha_programada, false), 'descripcion' => $v->descripcion, 'informe' => $v->informe,
                'cliente' => $v->contrato->empresa?->denominacion, 'contrato' => $v->contrato->codigo_contrato ?? "#{$v->contrato_id}",
                'edificio' => $edificio?->denominacion, 'direccion' => collect([$u['via'], $u['zona'], $u['municipio'], $u['departamento']])->filter()->implode(', '),
                'referencia' => $edificio?->referencia_cercana, 'latitud' => $edificio?->latitud, 'longitud' => $edificio?->longitud,
                'contacto' => $contacto ? ['nombre' => $contacto->nombre_completo, 'telefono' => $contacto->telefono_celular] : null,
                'equipos' => $edificio?->equipos->map(fn ($e) => ['codigo' => $e->codigo, 'descripcion' => $e->descripcion, 'detalle' => trim("{$e->marca} {$e->modelo}")])->values() ?? [],
            ];
        })->values()]);
    }

    public function completar(Request $request, string $id, NotificacionesClienteService $avisos): JsonResponse
    {
        $datos = $request->validate(['informe' => ['required', 'string', 'min:10', 'max:4000']]);

        $visita = VisitaMantenimiento::where('tecnico_id', $request->user()->personal_id)->findOrFail($id);
        abort_unless(in_array($visita->situacion, self::ABIERTAS, true), 422, 'Esta visita ya no está abierta.');

        $visita->update(['situacion' => 'REALIZADA', 'fecha_realizada' => today()->toDateString(), 'informe' => $datos['informe']]);
        $avisos->visitaRealizada($visita);

        return response()->json(['data' => ['id' => $visita->id, 'situacion' => 'REALIZADA']]);
    }
}
