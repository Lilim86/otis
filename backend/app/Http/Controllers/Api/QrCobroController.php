<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QrCobroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** QR de cobro: cualquiera con sesión lo ve (el cliente lo escanea en su portal); Cobranzas lo cambia. */
class QrCobroController extends Controller
{
    public function __construct(private QrCobroService $qr) {}

    public function datos(): JsonResponse
    {
        return response()->json(['data' => $this->qr->datos()]);
    }

    public function imagen(): Response
    {
        [$contenido, $tipo] = $this->qr->imagen();

        return response($contenido, 200, ['Content-Type' => $tipo, 'Cache-Control' => 'private, max-age=60']);
    }

    public function guardar(Request $request): JsonResponse
    {
        $d = $request->validate([
            'qr' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'banco' => ['required', 'string', 'max:100'],
            'cuenta' => ['required', 'string', 'max:40', 'regex:/^[0-9\- ]+$/'],
            'titular' => ['required', 'string', 'max:120'],
            'glosa' => ['nullable', 'string', 'max:120'],
            'monto' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'vence' => ['nullable', 'date'],
            'uso_unico' => ['nullable', 'boolean'],
            'prueba' => ['nullable', 'boolean'],
        ], ['qr.mimes' => 'El QR debe ser una imagen PNG o JPG.', 'qr.max' => 'La imagen del QR no puede pesar más de 2 MB.', 'cuenta.regex' => 'El número de cuenta solo lleva números y guiones.']);

        $imagen = $request->file('qr');
        unset($d['qr']);
        $d['monto'] = isset($d['monto']) && $d['monto'] !== '' ? (float) $d['monto'] : null;
        $d['uso_unico'] = (bool) ($d['uso_unico'] ?? false);

        return response()->json(['data' => $this->qr->guardar($imagen, $d, $request->user()->id), 'message' => 'QR de cobro actualizado. Los clientes ya lo ven en su portal.']);
    }

    public function restablecer(): JsonResponse
    {
        return response()->json(['data' => $this->qr->restablecer(), 'message' => 'Se volvió al QR de prueba.']);
    }
}
