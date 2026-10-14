<?php

namespace App\Http\Controllers\Api;

use App\Models\Cobranza;
use App\Models\CuotaContrato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CobranzaController extends CrudController
{
    protected string $modelo = Cobranza::class;

    // `estado` significa pagado/pendiente, no activo/inactivo.
    protected bool $usaEstado = false;

    protected bool $conAprobacion = true;

    protected array $con = ['contrato.empresa', 'cuota', 'cuentaBancaria.banco', 'cobrador'];

    protected array $buscar = ['concepto', 'nro_factura', 'nro_recibo', 'comprobante_deposito', 'factura_a_nombre'];

    protected array $filtros = ['contrato_id', 'cuota_id', 'cuenta_bancaria_id', 'cobrador_id'];

    protected array $fechas = ['fecha_cobro', 'fecha_facturacion'];

    protected array $orden = [['fecha_cobro', 'desc']];

    protected array $padres = ['contrato' => 'contrato'];

    protected function reglas(?Model $registro): array
    {
        $contrato = request()->input('contrato_id', $registro?->contrato_id);

        return [
            'contrato_id' => ['required', $this->existe('contratos')],
            // La cuota debe ser del mismo contrato de la cobranza.
            'cuota_id' => ['nullable', Rule::exists('cuotas_contrato', 'id')->whereNull('deleted_at')->where('contrato_id', $contrato)],
            'concepto' => ['required', 'string', 'max:255'],
            'monto_facturado' => ['required', 'numeric', 'min:0'],
            'fecha_cobro' => ['required', 'date'],
            'nro_factura' => ['nullable', 'string', 'max:255'],
            'fecha_facturacion' => ['nullable', 'date'],
            'nit_facturado' => ['nullable', 'string', 'max:30'],
            'factura_a_nombre' => ['nullable', 'string', 'max:255'],
            'cuenta_bancaria_id' => ['nullable', $this->existe('cuentas_bancarias')],
            'cobrador_id' => ['nullable', $this->existe('personas')],
            'comprobante_deposito' => ['nullable', 'string', 'max:40'],
            'nro_recibo' => ['nullable', 'string', 'max:40'],
            'cobrado_bs' => ['nullable', 'numeric', 'min:0'],
            'cobrado_usd' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function valoresPorDefecto(): array
    {
        // Una cobranza nueva nace pendiente.
        return ['estado' => false];
    }

    /** La cuota avanza sola: con factura queda FACTURADA y, si lo cobrado cubre el monto, COBRADA. */
    protected function despuesDeGuardar(Model $registro, Request $request): void
    {
        $cuota = $registro->cuota_id ? CuotaContrato::find($registro->cuota_id) : null;

        if (! $cuota || $cuota->estado_cuota === 'COBRADA') {
            return;
        }

        $cobrado = (float) Cobranza::where('cuota_id', $cuota->id)->sum('cobrado_bs');

        if ($cobrado >= (float) $cuota->monto_bs && $cobrado > 0) {
            $cuota->update(['estado_cuota' => 'COBRADA']);
        } elseif ($registro->nro_factura && $cuota->estado_cuota === 'PENDIENTE') {
            $cuota->update(['estado_cuota' => 'FACTURADA']);
        }
    }
}
