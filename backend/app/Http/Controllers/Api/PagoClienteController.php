<?php

namespace App\Http\Controllers\Api;

use App\Models\Cobranza;
use App\Models\CuentaBancaria;
use App\Models\PagoCliente;
use App\Services\NotificacionesClienteService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Pagos por transferencia declarados por los clientes. Financiero los verifica contra el extracto:
 * aprobar = confirmar (nace la cobranza y avanza la cuota), anular = rechazar con motivo.
 */
class PagoClienteController extends CrudController
{
    protected string $modelo = PagoCliente::class;

    protected bool $conAprobacion = true;

    protected array $con = ['empresa', 'contrato', 'cuota'];

    protected array $buscar = ['nro_transaccion', 'banco_origen', 'observacion'];

    protected array $filtros = ['empresa_id', 'contrato_id', 'cuota_id'];

    protected array $fechas = ['fecha_pago'];

    protected array $orden = [['fecha_pago', 'desc'], ['id', 'desc']];

    protected function reglas(?Model $registro): array
    {
        return [];
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Los pagos los registra el cliente desde su portal.'], 405);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        return response()->json(['message' => 'Un pago declarado no se modifica: se confirma o se rechaza.'], 405);
    }

    /** Confirma el pago: crea la cobranza con su comprobante y, si cubre la cuota, la marca COBRADA. */
    public function aprobar(string $id)
    {
        $pago = $this->buscarRegistro($id);

        DB::transaction(function () use ($pago) {
            $pago->aprobar();

            $cobranza = Cobranza::create([
                'contrato_id' => $pago->contrato_id,
                'cuota_id' => $pago->cuota_id,
                'concepto' => "Pago por transferencia ref. {$pago->nro_transaccion}",
                'monto_facturado' => $pago->monto_bs,
                'cobrado_bs' => $pago->monto_bs,
                'fecha_cobro' => $pago->fecha_pago,
                'comprobante_deposito' => mb_substr($pago->nro_transaccion, 0, 40),
                'cuenta_bancaria_id' => CuentaBancaria::where('numero_cuenta', $pago->cuenta_destino)->value('id'),
                'estado' => true,
            ]);

            $pago->forceFill(['cobranza_id' => $cobranza->id])->save();
            $pago->cuota?->actualizarPorCobros();
        });

        app(NotificacionesClienteService::class)->pagoResuelto($pago->fresh(), true);

        return $this->recurso($pago->fresh());
    }

    /** Rechaza el pago (no se encontró la transferencia, monto distinto, etc.). El cliente ve el motivo. */
    public function anular(string $id)
    {
        $pago = $this->buscarRegistro($id);
        $motivo = request()->validate(['motivo_rechazo' => ['required', 'string', 'max:500']])['motivo_rechazo'];

        DB::transaction(function () use ($pago, $motivo) {
            $pago->anularRegistro();
            $pago->forceFill(['motivo_rechazo' => $motivo])->save();
        });

        app(NotificacionesClienteService::class)->pagoResuelto($pago->fresh(), false);

        return $this->recurso($pago->fresh());
    }

    /** Foto o PDF de la transferencia que subió el cliente. */
    public function comprobante(string $id)
    {
        $pago = PagoCliente::findOrFail($id);
        abort_unless($pago->comprobante_path && Storage::exists($pago->comprobante_path), 404, 'Este pago no tiene comprobante adjunto.');

        return Storage::download($pago->comprobante_path, $pago->comprobante_nombre ?: 'comprobante');
    }
}
