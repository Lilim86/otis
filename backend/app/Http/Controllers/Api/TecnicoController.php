<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Insumo;
use App\Models\VisitaMantenimiento;
use App\Services\InventarioService;
use App\Services\NotificacionesClienteService;
use App\Services\UbicacionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Vista del técnico: sus visitas del día y de los próximos días, y cerrar una con su informe. */
class TecnicoController extends Controller
{
    private const ABIERTAS = ['PROGRAMADA', 'REPROGRAMADA', 'SOLICITADA', 'EN_CURSO'];

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

        // Dentro de cada día, en el orden de la ruta óptima (las urgencias P1 primero).
        $visitas = $consulta->orderBy('fecha_programada')->orderByRaw("CASE WHEN prioridad = 'P1' THEN 0 ELSE 1 END")->orderByRaw('orden_ruta IS NULL, orden_ruta')->get();

        return response()->json(['data' => $visitas->map(function (VisitaMantenimiento $v) use ($ubicacion) {
            $edificio = $v->contrato->edificio;
            $u = $ubicacion->de($edificio);
            $contacto = $edificio?->contactoTecnico;

            return [
                'id' => $v->id, 'tipo' => $v->tipo, 'nro_visita' => $v->nro_visita, 'situacion' => $v->situacion,
                'orden_ruta' => $v->orden_ruta, 'prioridad' => $v->prioridad, 'objetivo_respuesta_en' => $v->objetivo_respuesta_en?->toIso8601String(),
                'fecha_programada' => $v->fecha_programada->toDateString(), 'fecha_realizada' => $v->fecha_realizada?->toDateString(),
                'iniciada_en' => $v->iniciada_en?->toIso8601String(), 'dias' => (int) today()->diffInDays($v->fecha_programada, false), 'descripcion' => $v->descripcion, 'informe' => $v->informe,
                'cliente' => $v->contrato->empresa?->denominacion, 'contrato' => $v->contrato->codigo_contrato ?? "#{$v->contrato_id}",
                'edificio' => $edificio?->denominacion, 'direccion' => collect([$u['via'], $u['zona'], $u['municipio'], $u['departamento']])->filter()->implode(', '),
                'referencia' => $edificio?->referencia_cercana, 'latitud' => $edificio?->latitud, 'longitud' => $edificio?->longitud,
                'contacto' => $contacto ? ['nombre' => $contacto->nombre_completo, 'telefono' => $contacto->telefono_celular] : null,
                'equipos' => $edificio?->equipos->map(fn ($e) => ['codigo' => $e->codigo, 'descripcion' => $e->descripcion, 'detalle' => trim("{$e->marca} {$e->modelo}")])->values() ?? [],
            ];
        })->values()]);
    }

    /** El técnico avisa que llegó al edificio: queda "en curso" y la oficina lo ve en el cronograma. */
    public function iniciar(Request $request, string $id): JsonResponse
    {
        $visita = VisitaMantenimiento::where('tecnico_id', $request->user()->personal_id)->findOrFail($id);
        abort_unless(in_array($visita->situacion, ['PROGRAMADA', 'REPROGRAMADA', 'SOLICITADA'], true), 422, 'Esta visita no se puede iniciar.');
        abort_if(
            VisitaMantenimiento::where('tecnico_id', $visita->tecnico_id)->where('situacion', 'EN_CURSO')->exists(),
            422, 'Ya tienes otra visita en curso: termínala antes de empezar esta.'
        );

        $visita->update(['situacion' => 'EN_CURSO', 'iniciada_en' => now()]);

        return response()->json(['data' => ['id' => $visita->id, 'situacion' => 'EN_CURSO', 'iniciada_en' => $visita->iniciada_en->toIso8601String()]]);
    }

    /** Repuestos y materiales con existencias, para anotar lo que se usó en la visita. */
    public function materiales(Request $request): JsonResponse
    {
        $inventario = app(InventarioService::class);
        $bienes = Insumo::where('estado', true)->where('stock_actual', '>', 0)->where(fn ($q) => $q->whereNull('tipo_almacen')->orWhere('tipo_almacen', '!=', 'H'))->orderBy('descripcion')
            ->get(['id', 'codigo', 'descripcion', 'unidad_medida', 'stock_actual'])->map(fn ($i) => [
                'id' => $i->id, 'codigo' => $i->codigo, 'descripcion' => $i->descripcion, 'unidad' => $i->unidad_medida,
                'stock' => (float) $i->stock_actual, 'almacenes' => $inventario->existencias($i->id),
            ])->filter(fn ($i) => $i['almacenes'])->values();

        return response()->json(['data' => $bienes]);
    }

    /** Herramientas que este técnico tiene prestadas. */
    public function herramientas(Request $request): JsonResponse
    {
        $mias = collect(app(InventarioService::class)->herramientasEnPoder())->where('personal_id', $request->user()->personal_id)->values();

        return response()->json(['data' => $mias]);
    }

    public function completar(Request $request, string $id, NotificacionesClienteService $avisos, InventarioService $inventario): JsonResponse
    {
        $datos = $request->validate([
            'informe' => ['required', 'string', 'min:10', 'max:4000'],
            // Lo que se gastó en la visita: sale del almacén automáticamente.
            'materiales' => ['nullable', 'array', 'max:30'],
            'materiales.*.insumo_id' => ['required', Rule::exists('insumos', 'id')->whereNull('deleted_at')],
            'materiales.*.almacen_id' => ['required', Rule::exists('almacenes', 'id')->whereNull('deleted_at')],
            'materiales.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:999999'],
        ]);

        $visita = VisitaMantenimiento::where('tecnico_id', $request->user()->personal_id)->findOrFail($id);
        abort_unless(in_array($visita->situacion, self::ABIERTAS, true), 422, 'Esta visita ya no está abierta.');

        DB::transaction(function () use ($visita, $datos, $inventario) {
            foreach ($datos['materiales'] ?? [] as $m) {
                $inventario->registrar([
                    'almacen_id' => $m['almacen_id'], 'insumo_id' => $m['insumo_id'], 'tipo' => 'SALIDA', 'cantidad' => $m['cantidad'],
                    'fecha' => today()->toDateString(), 'motivo' => 'CONSUMO', 'personal_id' => $visita->tecnico_id, 'visita_id' => $visita->id,
                    'referencia' => "Visita #{$visita->id}", 'costo_unitario_bs' => Insumo::find($m['insumo_id'])?->precio_compra_bs ?? 0,
                ]);
            }
            $visita->update(['situacion' => 'REALIZADA', 'fecha_realizada' => today()->toDateString(), 'informe' => $datos['informe']]);
        });
        $avisos->visitaRealizada($visita);

        return response()->json(['data' => ['id' => $visita->id, 'situacion' => 'REALIZADA']]);
    }
}
