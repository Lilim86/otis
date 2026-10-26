<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ComprobanteMail;
use App\Models\Cobranza;
use App\Models\EnvioComprobante;
use App\Services\ComprobanteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** Facturas y recibos de las cobranzas: ver el PDF, enviarlo por correo al cliente y ver los modelos de diseño. */
class ComprobanteController extends Controller
{
    public function __construct(private ComprobanteService $comprobantes) {}

    private function tipo(string $tipo): string
    {
        abort_unless(in_array($tipo, ComprobanteService::TIPOS, true), 404, 'Tipo de comprobante no válido.');

        return $tipo;
    }

    /** PDF de ejemplo con datos inventados, para revisar el diseño. */
    public function modelo(string $tipo): Response
    {
        return $this->comprobantes->modelo($this->tipo($tipo))->stream("Modelo_{$tipo}.pdf");
    }

    public function ver(string $id, string $tipo): Response
    {
        $cobranza = $this->cobranza($id, $this->tipo($tipo));

        return $this->comprobantes->pdf($cobranza, $tipo)->stream($this->comprobantes->nombreArchivo($cobranza, $tipo));
    }

    public function enviar(Request $request, string $id): JsonResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(ComprobanteService::TIPOS)],
            'correo' => ['nullable', 'email', 'max:255'],
        ]);

        $cobranza = $this->cobranza($id, $datos['tipo']);
        $destino = $datos['correo'] ?? $cobranza->contrato?->empresa?->correo_contacto;

        if (! $destino) {
            return response()->json(['message' => 'La empresa no tiene correo registrado. Escribe el correo al que se enviará.', 'errors' => ['correo' => ['Falta el correo de destino.']]], 422);
        }

        $d = $this->comprobantes->datos($cobranza, $datos['tipo']);
        $envio = ['cobranza_id' => $cobranza->id, 'tipo' => strtoupper($datos['tipo']), 'destinatario' => $destino, 'enviado_por' => $request->user()->id];

        try {
            Mail::to($destino)->send(new ComprobanteMail(
                $d['cliente']['nombre'], $datos['tipo'], $d['numero'], number_format($d['total'], 2),
                $this->comprobantes->nombreArchivo($cobranza, $datos['tipo']), $this->comprobantes->pdf($cobranza, $datos['tipo'])->output(),
            ));
        } catch (\Throwable $e) {
            report($e);
            EnvioComprobante::create($envio + ['resultado' => 'FALLIDO', 'error' => mb_substr($e->getMessage(), 0, 500)]);

            return response()->json(['message' => 'No se pudo enviar el correo. Inténtalo más tarde.'], 503);
        }

        EnvioComprobante::create($envio + ['resultado' => 'ENVIADO']);

        return response()->json(['message' => "{$d['numero']} enviado a {$destino}.", 'destinatario' => $destino]);
    }

    public function envios(string $id): JsonResponse
    {
        $envios = EnvioComprobante::where('cobranza_id', Cobranza::findOrFail($id)->id)->orderByDesc('id')->get();

        return response()->json(['data' => $envios]);
    }

    private function cobranza(string $id, string $tipo): Cobranza
    {
        $cobranza = Cobranza::with(['contrato.empresa', 'contrato.edificio', 'cuota', 'cuentaBancaria.banco'])->findOrFail($id);
        abort_if($cobranza->estado_registro === 'ANL', 422, 'La cobranza está anulada.');
        abort_if($tipo === 'recibo' && (float) $cobranza->cobrado_bs <= 0, 422, 'Esta cobranza todavía no tiene un monto cobrado: no se puede emitir el recibo.');

        return $cobranza;
    }
}
