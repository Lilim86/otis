<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cobranza;
use App\Models\CuentaBancaria;
use App\Services\CobradorService;
use App\Services\ComprobanteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Portal del cobrador: su ruta, sus cobros con factura y recibo, y el envío a Tesorería. Todo limitado a lo suyo. */
class CobradorController extends Controller
{
    public function __construct(private CobradorService $servicio) {}

    public function ruta(Request $request): JsonResponse
    {
        // Las cuentas de la empresa van aparte: el cobrador elige a cuál transfirió el cliente.
        $cuentas = CuentaBancaria::with('banco')->where('estado', true)->get()
            ->map(fn ($c) => ['id' => $c->id, 'texto' => trim(($c->banco?->sigla ?: $c->banco?->nombre)." · {$c->numero_cuenta} ({$c->moneda})")]);

        return response()->json(['data' => $this->servicio->ruta($request->user()->persona_id), 'cuentas' => $cuentas]);
    }

    public function cobros(Request $request): JsonResponse
    {
        $f = $request->validate(['situacion' => ['nullable', Rule::in(array_keys(config('sofia.situaciones_tesoreria')))]]);

        $cobros = Cobranza::with(['contrato.empresa', 'contrato.edificio', 'cuota', 'cuentaBancaria.banco'])
            ->where('cobrador_id', $request->user()->persona_id)->whereNotNull('situacion_tesoreria')
            ->when($f['situacion'] ?? null, fn ($q, $s) => $q->where('situacion_tesoreria', $s))
            ->orderByDesc('fecha_cobro')->orderByDesc('id')->limit(200)->get();

        return response()->json(['data' => $cobros]);
    }

    public function registrar(Request $request): JsonResponse
    {
        $cobranza = $this->servicio->registrar($request->user(), $this->validar($request, true));

        return response()->json(['message' => 'Cobro registrado. Ya puedes ver su factura y su recibo.', 'data' => $this->cargar($cobranza)], 201);
    }

    public function actualizar(Request $request, string $id): JsonResponse
    {
        $cobranza = $this->servicio->actualizar($this->propio($request, $id), $this->validar($request, false));

        return response()->json(['data' => $this->cargar($cobranza)]);
    }

    public function eliminar(Request $request, string $id): Response
    {
        $this->servicio->eliminar($this->propio($request, $id));

        return response()->noContent();
    }

    /** Envía a Tesorería los cobros elegidos (o todos los que están por enviar u observados). */
    public function enviar(Request $request): JsonResponse
    {
        $f = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:200'], 'ids.*' => ['integer']]);
        $enviados = $this->servicio->enviar($request->user()->persona_id, $f['ids']);

        abort_if($enviados === 0, 422, 'No hay cobros por enviar entre los elegidos.');

        return response()->json(['message' => "Enviaste {$enviados} cobro(s) a Tesorería.", 'enviados' => $enviados]);
    }

    public function comprobante(Request $request, string $id, string $tipo): Response
    {
        abort_unless(in_array($tipo, ComprobanteService::TIPOS, true), 404, 'Tipo de comprobante no válido.');
        $cobranza = $this->propio($request, $id);
        $comprobantes = app(ComprobanteService::class);

        return $comprobantes->pdf($cobranza, $tipo)->stream($comprobantes->nombreArchivo($cobranza, $tipo));
    }

    private function validar(Request $request, bool $nuevo): array
    {
        $metodo = $request->input('metodo_pago');

        return $request->validate([
            'cuota_id' => [$nuevo ? 'required' : 'prohibited', 'integer'],
            'tipo_cobro' => ['required', Rule::in(array_keys(config('sofia.tipos_cobro')))],
            'metodo_pago' => ['required', Rule::in(array_keys(config('sofia.metodos_pago')))],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'fecha_cobro' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.today()->subDays(60)->toDateString()],
            'nro_recibo' => ['nullable', 'string', 'max:40'],
            'factura_a_nombre' => ['nullable', 'string', 'max:255'],
            'nit_facturado' => ['nullable', 'string', 'max:30'],
            'nro_cheque' => [Rule::requiredIf($metodo === 'CHEQUE'), 'nullable', 'string', 'max:30'],
            'banco_cheque' => [Rule::requiredIf($metodo === 'CHEQUE'), 'nullable', 'string', 'max:80'],
            'nro_transferencia' => [Rule::requiredIf($metodo === 'TRANSFERENCIA'), 'nullable', 'string', 'max:40'],
            'cuenta_bancaria_id' => [Rule::requiredIf($metodo === 'TRANSFERENCIA'), 'nullable', Rule::exists('cuentas_bancarias', 'id')->whereNull('deleted_at')->where('estado', true)],
        ], [
            'nro_cheque.required' => 'Anota el número del cheque.',
            'banco_cheque.required' => 'Anota el banco del cheque.',
            'nro_transferencia.required' => 'Anota el número de la transferencia.',
            'cuenta_bancaria_id.required' => 'Elige la cuenta a la que transfirió el cliente.',
            'fecha_cobro.after_or_equal' => 'La fecha del cobro no puede tener más de 60 días.',
        ]);
    }

    private function propio(Request $request, string $id): Cobranza
    {
        return Cobranza::where('cobrador_id', $request->user()->persona_id)->whereNotNull('situacion_tesoreria')->findOrFail($id);
    }

    private function cargar(Cobranza $cobranza): Cobranza
    {
        return $cobranza->refresh()->load(['contrato.empresa', 'contrato.edificio', 'cuota', 'cuentaBancaria.banco']);
    }
}
