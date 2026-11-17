<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cobranza;
use App\Services\CobradorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Tesorería revisa lo que mandan los cobradores: aprueba (nace el ingreso) u observa (vuelve al cobrador). */
class CobrosCobradoresController extends Controller
{
    public function __construct(private CobradorService $servicio) {}

    public function index(Request $request): JsonResponse
    {
        $f = $request->validate(['situacion' => ['nullable', Rule::in(['ENVIADO', 'OBSERVADO', 'APROBADO'])]]);
        $situacion = $f['situacion'] ?? 'ENVIADO';

        return response()->json([
            'data' => $this->servicio->bandeja($situacion),
            'por_aprobar' => Cobranza::where('situacion_tesoreria', 'ENVIADO')->count(),
        ]);
    }

    public function aprobar(Request $request, string $id): JsonResponse
    {
        $f = $request->validate([
            'cuenta_bancaria_id' => ['required', Rule::exists('cuentas_bancarias', 'id')->whereNull('deleted_at')->where('estado', true)],
        ], ['cuenta_bancaria_id.required' => 'Elige la cuenta donde entra el dinero.']);

        $cobranza = $this->servicio->aprobar(Cobranza::findOrFail($id), (int) $f['cuenta_bancaria_id'], $request->user());

        return response()->json(['message' => 'Cobro aprobado: ya está registrado el ingreso en Tesorería.', 'data' => $cobranza]);
    }

    public function observar(Request $request, string $id): JsonResponse
    {
        // Observar es la otra cara de aprobar: exige el mismo permiso.
        abort_unless($this->puedeAprobar($request), 403, 'No tienes permiso para esta operación (ingresos-tesoreria.aprobar).');
        $f = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']], ['motivo.required' => 'Escribe qué tiene que corregir el cobrador.']);

        $cobranza = $this->servicio->observar(Cobranza::findOrFail($id), $f['motivo'], $request->user());

        return response()->json(['message' => 'Cobro devuelto al cobrador con tu observación.', 'data' => $cobranza]);
    }

    private function puedeAprobar(Request $request): bool
    {
        $u = $request->user();

        return ! config('sofia.permisos_activos') || $u->id === 1 || $u->can('ingresos-tesoreria.aprobar');
    }
}
