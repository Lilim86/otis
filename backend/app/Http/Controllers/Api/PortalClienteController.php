<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cobranza;
use App\Models\Contrato;
use App\Models\CuotaContrato;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\PagoCliente;
use App\Models\VisitaMantenimiento;
use App\Services\ComprobanteService;
use App\Services\CronogramaService;
use App\Services\UbicacionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Portal del cliente: todo lo que consulta o hace una empresa cliente, siempre limitado a lo suyo. */
class PortalClienteController extends Controller
{
    private function empresaId(Request $request): int
    {
        return (int) $request->user()->empresa_id;
    }

    /** Contratos vigentes o pasados de la empresa, sin los anulados. */
    private function contratos(Request $request)
    {
        return Contrato::where('empresa_id', $this->empresaId($request))->where('estado_registro', '!=', 'ANL');
    }

    private function contratoPropio(Request $request, int $id): Contrato
    {
        return $this->contratos($request)->findOrFail($id);
    }

    // ------------------------------------------------------------------ resumen

    public function resumen(Request $request): JsonResponse
    {
        $empresa = $request->user()->empresa;
        $contratos = $this->contratos($request)->with('edificio')->get();
        $ids = $contratos->pluck('id');
        $edificios = $contratos->pluck('edificio_id')->merge(Edificio::where('empresa_id', $empresa->id)->pluck('id'))->unique();

        $visitas = VisitaMantenimiento::with('contrato.edificio')->whereIn('contrato_id', $ids);
        $proxima = (clone $visitas)->whereIn('situacion', ['PROGRAMADA', 'SOLICITADA', 'REPROGRAMADA'])->whereDate('fecha_programada', '>=', today())->orderBy('fecha_programada')->first();
        $ultima = (clone $visitas)->where('situacion', 'REALIZADA')->orderByDesc('fecha_realizada')->first();

        $cuotas = $this->cuotasConSaldo($request)->where('saldo', '>', 0);
        $notas = (clone $visitas)->where('situacion', 'REALIZADA')->whereNotNull('calificacion')->avg('calificacion');

        return response()->json(['data' => [
            'empresa' => ['id' => $empresa->id, 'denominacion' => $empresa->denominacion, 'nit' => $empresa->nit],
            'contratos_vigentes' => $contratos->filter(fn ($c) => Carbon::parse($c->fecha_fin)->gte(today()))->count(),
            'edificios' => $edificios->count(),
            'equipos' => Equipo::whereIn('edificio_id', $edificios)->count(),
            'proxima_visita' => $proxima ? $this->visita($proxima) + ['dias' => (int) today()->diffInDays($proxima->fecha_programada, false)] : null,
            'ultima_visita' => $ultima ? $this->visita($ultima) : null,
            'visitas_realizadas' => (clone $visitas)->where('situacion', 'REALIZADA')->count(),
            'solicitudes_abiertas' => (clone $visitas)->where('situacion', 'SOLICITADA')->count(),
            'calificacion_promedio' => $notas ? round((float) $notas, 1) : null,
            'cuotas_pendientes' => $cuotas->count(),
            'cuotas_vencidas' => $cuotas->filter(fn ($c) => $c['vencida'])->count(),
            'saldo_pendiente_bs' => round($cuotas->sum('saldo'), 2),
            'pagos_en_verificacion' => PagoCliente::where('empresa_id', $empresa->id)->where('estado_registro', 'REG')->count(),
        ]]);
    }

    // ------------------------------------------------------------------ contratos, edificios y equipos

    public function contratosIndex(Request $request): JsonResponse
    {
        $ubicacion = new UbicacionResolver;
        $cobrado = Cobranza::where('estado_registro', '!=', 'ANL')->groupBy('contrato_id')->selectRaw('contrato_id, sum(cobrado_bs) as bs')->pluck('bs', 'contrato_id');

        $filas = $this->contratos($request)->with(['edificio', 'visitas'])->orderByDesc('fecha_inicio')->get()->map(function (Contrato $c) use ($ubicacion, $cobrado) {
            $visitas = $c->visitas->where('tipo', 'PREVENTIVO');
            $proxima = $visitas->whereIn('situacion', ['PROGRAMADA', 'REPROGRAMADA'])->where('fecha_programada', '>=', today())->sortBy('fecha_programada')->first();

            return [
                'id' => $c->id, 'codigo' => $c->codigo_contrato ?? "#{$c->id}",
                'edificio' => $c->edificio?->denominacion, 'ubicacion' => $ubicacion->de($c->edificio),
                'fecha_inicio' => Carbon::parse($c->fecha_inicio)->toDateString(), 'fecha_fin' => Carbon::parse($c->fecha_fin)->toDateString(),
                'vigente' => Carbon::parse($c->fecha_fin)->gte(today()),
                'monto_total' => (float) $c->monto_total, 'cobrado_bs' => round((float) ($cobrado[$c->id] ?? 0), 2),
                'saldo_bs' => round((float) $c->monto_total - (float) ($cobrado[$c->id] ?? 0), 2),
                'visitas_programadas' => $visitas->count(), 'visitas_realizadas' => $visitas->where('situacion', 'REALIZADA')->count(),
                'proxima_visita' => $proxima?->fecha_programada?->toDateString(),
            ];
        })->values();

        return response()->json(['data' => $filas]);
    }

    /** Edificios del cliente con sus equipos (ascensores) y la fecha de su último mantenimiento. */
    public function equipos(Request $request): JsonResponse
    {
        $contratos = $this->contratos($request)->get();
        $edificios = Edificio::with('equipos')->whereIn('id', $contratos->pluck('edificio_id')->merge(Edificio::where('empresa_id', $this->empresaId($request))->pluck('id'))->unique())->get();
        $ubicacion = new UbicacionResolver;

        $ultimas = VisitaMantenimiento::whereIn('contrato_id', $contratos->pluck('id'))->where('situacion', 'REALIZADA')
            ->get()->groupBy(fn ($v) => $contratos->firstWhere('id', $v->contrato_id)?->edificio_id)->map(fn ($g) => $g->max('fecha_realizada'));

        return response()->json(['data' => $edificios->map(fn (Edificio $e) => [
            'id' => $e->id, 'codigo' => $e->codigo_edificio, 'denominacion' => $e->denominacion, 'tipo' => $e->tipo_edificacion,
            'ubicacion' => $ubicacion->de($e), 'ultimo_mantenimiento' => isset($ultimas[$e->id]) ? Carbon::parse($ultimas[$e->id])->toDateString() : null,
            'equipos' => $e->equipos->map(fn ($q) => ['id' => $q->id, 'codigo' => $q->codigo, 'descripcion' => $q->descripcion, 'marca' => $q->marca, 'modelo' => $q->modelo, 'paradas' => $q->paradas])->values(),
        ])->values()]);
    }

    // ------------------------------------------------------------------ mantenimiento

    private function visita(VisitaMantenimiento $v): array
    {
        return [
            'id' => $v->id, 'contrato_id' => $v->contrato_id, 'contrato' => $v->contrato?->codigo_contrato ?? "#{$v->contrato_id}",
            'edificio' => $v->contrato?->edificio?->denominacion, 'tipo' => $v->tipo, 'nro_visita' => $v->nro_visita,
            'fecha_programada' => $v->fecha_programada?->toDateString(), 'fecha_realizada' => $v->fecha_realizada?->toDateString(),
            'situacion' => $v->situacion, 'tecnico' => $v->tecnico ? trim("{$v->tecnico->nombres} {$v->tecnico->apellidos}") : null,
            'descripcion' => $v->descripcion, 'informe' => $v->informe, 'calificacion' => $v->calificacion, 'comentario_cliente' => $v->comentario_cliente,
            'firmada' => $v->firma_cliente !== null, 'firmado_por' => $v->firmado_por,
        ];
    }

    /** Historial de mantenimiento: lo realizado, lo programado y lo solicitado. */
    public function mantenimientos(Request $request): JsonResponse
    {
        $f = $request->validate([
            'contrato_id' => ['nullable', 'integer'], 'tipo' => ['nullable', Rule::in(['PREVENTIVO', 'CORRECTIVO'])],
            'situacion' => ['nullable', Rule::in(array_keys(config('sofia.situaciones_visita')))],
            'desde' => ['nullable', 'date'], 'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $visitas = VisitaMantenimiento::with(['contrato.edificio', 'tecnico'])->whereIn('contrato_id', $this->contratos($request)->pluck('id'))
            ->when($f['contrato_id'] ?? null, fn ($q, $v) => $q->where('contrato_id', $v))
            ->when($f['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->when($f['situacion'] ?? null, fn ($q, $v) => $q->where('situacion', $v))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->whereDate('fecha_programada', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->whereDate('fecha_programada', '<=', $v))
            ->orderByDesc('fecha_programada')->limit(500)->get();

        return response()->json(['data' => $visitas->map(fn ($v) => $this->visita($v))->values()]);
    }

    /** El cliente pide una visita correctiva (falla, ruido, ascensor detenido…). */
    public function solicitar(Request $request, CronogramaService $cronograma): JsonResponse
    {
        $d = $request->validate([
            'contrato_id' => ['required', 'integer'],
            'descripcion' => ['required', 'string', 'min:10', 'max:1000'],
            'urgente' => ['nullable', 'boolean'],
            'fecha_deseada' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $contrato = $this->contratoPropio($request, $d['contrato_id']);
        abort_unless(Carbon::parse($contrato->fecha_fin)->gte(today()), 422, 'El contrato ya no está vigente.');

        $fecha = isset($d['fecha_deseada']) ? Carbon::parse($d['fecha_deseada']) : $cronograma->sumarDiasHabiles(today(), 1);

        $visita = VisitaMantenimiento::create([
            'contrato_id' => $contrato->id, 'tipo' => 'CORRECTIVO', 'situacion' => 'SOLICITADA', 'fecha_programada' => $fecha->toDateString(),
            'descripcion' => (($d['urgente'] ?? false) ? '[URGENTE] ' : '').$d['descripcion'], 'solicitada_por' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->visita($visita->load('contrato.edificio'))], 201);
    }

    /** Conformidad del cliente con una visita realizada: 1 a 5 y un comentario. */
    public function calificar(Request $request, int $id): JsonResponse
    {
        $d = $request->validate(['calificacion' => ['required', 'integer', 'between:1,5'], 'comentario' => ['nullable', 'string', 'max:500']]);

        $visita = VisitaMantenimiento::whereIn('contrato_id', $this->contratos($request)->pluck('id'))->findOrFail($id);
        abort_unless($visita->situacion === 'REALIZADA', 422, 'Solo se califican las visitas ya realizadas.');

        $visita->update(['calificacion' => $d['calificacion'], 'comentario_cliente' => $d['comentario'] ?? null]);

        return response()->json(['data' => $this->visita($visita->load(['contrato.edificio', 'tecnico']))]);
    }

    /** Cronograma preventivo (cada 30 días hábiles) del contrato, o de todos si no se indica. */
    public function cronograma(Request $request): JsonResponse
    {
        $contratoId = $request->validate(['contrato_id' => ['nullable', 'integer']])['contrato_id'] ?? null;
        $contratos = $this->contratos($request)->when($contratoId, fn ($q) => $q->where('id', $contratoId))->get();

        $visitas = VisitaMantenimiento::with(['contrato.edificio', 'tecnico'])->whereIn('contrato_id', $contratos->pluck('id'))
            ->where('tipo', 'PREVENTIVO')->orderBy('fecha_programada')->get();

        return response()->json([
            'intervalo_dias_habiles' => (int) config('sofia.mantenimiento.intervalo_dias_habiles'),
            'data' => $visitas->map(fn ($v) => $this->visita($v) + [
                'dias' => $v->situacion === 'REALIZADA' ? null : (int) today()->diffInDays($v->fecha_programada, false),
            ])->values(),
        ]);
    }

    // ------------------------------------------------------------------ cuotas y pagos

    /** @return Collection<int, array> */
    private function cuotasConSaldo(Request $request): Collection
    {
        $enVerificacion = PagoCliente::where('empresa_id', $this->empresaId($request))->where('estado_registro', 'REG')->whereNotNull('cuota_id')
            ->groupBy('cuota_id')->selectRaw('cuota_id, sum(monto_bs) as bs')->pluck('bs', 'cuota_id');

        return CuotaContrato::with('contrato.edificio')->whereIn('contrato_id', $this->contratos($request)->pluck('id'))->orderBy('fecha_cuota')->get()->map(function (CuotaContrato $c) use ($enVerificacion) {
            $cobrado = $c->cobrado();
            $verificando = (float) ($enVerificacion[$c->id] ?? 0);

            return [
                'id' => $c->id, 'contrato_id' => $c->contrato_id, 'contrato' => $c->contrato->codigo_contrato ?? "#{$c->contrato_id}",
                'edificio' => $c->contrato->edificio?->denominacion, 'nro_cuota' => $c->nro_cuota, 'fecha_cuota' => $c->fecha_cuota->toDateString(),
                'concepto' => $c->concepto, 'estado_cuota' => $c->estado_cuota, 'monto_bs' => (float) $c->monto_bs, 'cobrado_bs' => round($cobrado, 2),
                'en_verificacion_bs' => round($verificando, 2), 'saldo' => max(0, round((float) $c->monto_bs - $cobrado - $verificando, 2)),
                'vencida' => $c->estado_cuota !== 'COBRADA' && $c->fecha_cuota->lt(today()),
            ];
        });
    }

    /** Estado de cuenta: cuotas de todos los contratos con lo cobrado y lo que falta. */
    public function cuotas(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->cuotasConSaldo($request)->values()]);
    }

    /** A dónde transferir. */
    public function datosCuenta(): JsonResponse
    {
        return response()->json(['data' => config('sofia.pagos')]);
    }

    public function pagos(Request $request): JsonResponse
    {
        $pagos = PagoCliente::with(['contrato', 'cuota'])->where('empresa_id', $this->empresaId($request))->orderByDesc('fecha_pago')->orderByDesc('id')->get();

        return response()->json(['data' => $pagos->map(fn (PagoCliente $p) => [
            'id' => $p->id, 'contrato' => $p->contrato?->codigo_contrato ?? "#{$p->contrato_id}", 'nro_cuota' => $p->cuota?->nro_cuota,
            'monto_bs' => (float) $p->monto_bs, 'fecha_pago' => $p->fecha_pago->toDateString(), 'nro_transaccion' => $p->nro_transaccion,
            'banco_origen' => $p->banco_origen, 'cuenta_destino' => $p->cuenta_destino, 'observacion' => $p->observacion,
            'situacion' => ['REG' => 'EN_VERIFICACION', 'APR' => 'CONFIRMADO', 'ANL' => 'RECHAZADO'][$p->estado_registro] ?? $p->estado_registro,
            'motivo_rechazo' => $p->motivo_rechazo, 'tiene_comprobante' => $p->comprobante_path !== null,
            'recibo_disponible' => $p->estado_registro === 'APR' && $p->cobranza_id !== null,
        ])->values()]);
    }

    /** El cliente declara la transferencia que hizo; Financiero la verifica. No mueve dinero. */
    public function pagar(Request $request): JsonResponse
    {
        $cuentaDestino = config('sofia.pagos.cuenta_destino');
        abort_unless($cuentaDestino, 503, 'Los pagos por transferencia todavía no están habilitados.');

        $d = $request->validate([
            'cuota_id' => ['required', 'integer'],
            'monto_bs' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'fecha_pago' => ['required', 'date', 'before_or_equal:today'],
            'nro_transaccion' => ['required', 'string', 'min:4', 'max:60', 'regex:/^[A-Za-z0-9\-\/ ]+$/'],
            'banco_origen' => ['nullable', 'string', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:500'],
            'comprobante' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ], ['comprobante.mimes' => 'El comprobante debe ser una foto (JPG o PNG) o un PDF.', 'comprobante.max' => 'El comprobante no puede pesar más de 4 MB.', 'nro_transaccion.regex' => 'El número de transacción solo lleva letras, números, guiones y barras.']);

        $cuota = $this->cuotasConSaldo($request)->firstWhere('id', (int) $d['cuota_id']);
        abort_unless($cuota, 422, 'Esa cuota no es de tus contratos.');
        abort_if($cuota['saldo'] <= 0, 422, 'Esa cuota ya está cubierta o tiene un pago en verificación por el total.');

        if (round((float) $d['monto_bs'], 2) > $cuota['saldo']) {
            return response()->json(['message' => 'El monto supera el saldo de la cuota.', 'errors' => ['monto_bs' => ["El saldo de la cuota es Bs {$cuota['saldo']}."]]], 422);
        }

        if (PagoCliente::withTrashed()->where('cuenta_destino', $cuentaDestino)->where('nro_transaccion', $d['nro_transaccion'])->exists()) {
            return response()->json(['message' => 'Ese número de transacción ya fue registrado.', 'errors' => ['nro_transaccion' => ['Ya se registró un pago con ese número de transacción.']]], 422);
        }

        $archivo = $request->file('comprobante');
        unset($d['comprobante']);

        $pago = PagoCliente::create($d + [
            'empresa_id' => $this->empresaId($request), 'contrato_id' => $cuota['contrato_id'], 'user_id' => $request->user()->id, 'cuenta_destino' => $cuentaDestino,
        ]);

        if ($archivo) {
            $pago->forceFill([
                'comprobante_path' => $archivo->store("pagos/{$pago->empresa_id}"),
                'comprobante_nombre' => mb_substr($archivo->getClientOriginalName(), 0, 200),
            ])->save();
        }

        return response()->json(['data' => ['id' => $pago->id, 'situacion' => 'EN_VERIFICACION'], 'message' => 'Pago registrado. Lo verificaremos contra nuestro extracto bancario.'], 201);
    }

    private function pagoPropio(Request $request, int $id): PagoCliente
    {
        return PagoCliente::where('empresa_id', $this->empresaId($request))->findOrFail($id);
    }

    /** El cliente vuelve a ver el comprobante que subió. */
    public function comprobantePago(Request $request, int $id)
    {
        $pago = $this->pagoPropio($request, $id);
        abort_unless($pago->comprobante_path && Storage::exists($pago->comprobante_path), 404, 'Este pago no tiene comprobante adjunto.');

        return Storage::download($pago->comprobante_path, $pago->comprobante_nombre ?: 'comprobante');
    }

    /** Recibo de un pago ya confirmado. */
    public function reciboPago(Request $request, int $id, ComprobanteService $comprobantes)
    {
        $pago = $this->pagoPropio($request, $id);
        abort_unless($pago->estado_registro === 'APR' && $pago->cobranza, 404, 'El recibo estará disponible cuando confirmemos tu pago.');

        return $comprobantes->pdf($pago->cobranza, 'recibo')->stream($comprobantes->nombreArchivo($pago->cobranza, 'recibo'));
    }

    private function visitaPropia(Request $request, int $id): VisitaMantenimiento
    {
        return VisitaMantenimiento::whereIn('contrato_id', $this->contratos($request)->pluck('id'))->findOrFail($id);
    }

    /** Informe de una visita realizada, en PDF. */
    public function informeVisita(Request $request, int $id, ComprobanteService $comprobantes)
    {
        $visita = $this->visitaPropia($request, $id);
        abort_unless($visita->situacion === 'REALIZADA', 404, 'El informe estará disponible cuando se realice la visita.');

        return $comprobantes->informeVisita($visita)->stream("Informe_visita_{$id}.pdf");
    }

    /**
     * Firma de conformidad dibujada por el cliente. Llega como trazos (listas de puntos) y el servidor arma el SVG:
     * así nunca se guarda marcado ajeno y no hace falta la extensión GD para imprimirla en el PDF.
     */
    public function firmar(Request $request, int $id): JsonResponse
    {
        $d = $request->validate([
            'ancho' => ['required', 'integer', 'between:100,1200'],
            'alto' => ['required', 'integer', 'between:50,600'],
            'trazos' => ['required', 'array', 'min:1', 'max:80'],
            'trazos.*' => ['required', 'array', 'min:2', 'max:600'],
            'trazos.*.*' => ['required', 'array', 'size:2'],
            'trazos.*.*.*' => ['required', 'numeric', 'between:-5,1300'],
            'firmado_por' => ['required', 'string', 'min:3', 'max:120'],
        ], ['trazos.required' => 'Dibuja tu firma antes de enviarla.', 'trazos.min' => 'Dibuja tu firma antes de enviarla.']);

        $visita = $this->visitaPropia($request, $id);
        abort_unless($visita->situacion === 'REALIZADA', 422, 'Solo se firman las visitas ya realizadas.');
        abort_if($visita->firma_cliente !== null, 422, 'Esta visita ya fue firmada.');

        $trazos = collect($d['trazos'])->map(fn ($puntos) => 'M'.collect($puntos)->map(fn ($p) => round((float) $p[0], 1).' '.round((float) $p[1], 1))->implode(' L'));
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d">%s</svg>',
            $d['ancho'], $d['alto'], $d['ancho'], $d['alto'],
            $trazos->map(fn ($t) => '<path d="'.$t.'" fill="none" stroke="#0D1638" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>')->implode(''),
        );

        $visita->forceFill(['firma_cliente' => 'data:image/svg+xml;base64,'.base64_encode($svg), 'firmado_por' => $d['firmado_por'], 'firmado_en' => now()])->save();

        return response()->json(['data' => $this->visita($visita->load(['contrato.edificio', 'tecnico']))]);
    }
}
