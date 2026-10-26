<?php

namespace App\Services;

use App\Models\Cobranza;
use App\Models\VisitaMantenimiento;
use App\Support\NumeroLetras;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Carbon;

/** Arma los datos y el PDF de facturas, recibos e informes de visita. */
class ComprobanteService
{
    public const TIPOS = ['factura', 'recibo'];

    public function pdf(Cobranza $cobranza, string $tipo): DomPdf
    {
        return $this->renderizar($tipo, $this->datos($cobranza, $tipo));
    }

    /** Documento de muestra con datos inventados, para ver el diseño. */
    public function modelo(string $tipo): DomPdf
    {
        return $this->renderizar($tipo, $this->datosModelo($tipo));
    }

    public function nombreArchivo(Cobranza $cobranza, string $tipo): string
    {
        return ucfirst($tipo).'_'.preg_replace('/[^A-Za-z0-9\-]/', '', $this->numero($cobranza, $tipo)).'.pdf';
    }

    private function renderizar(string $tipo, array $datos): DomPdf
    {
        return Pdf::loadView("comprobantes.{$tipo}", ['d' => $datos])->setPaper('letter');
    }

    public function numero(Cobranza $c, string $tipo): string
    {
        return $tipo === 'factura'
            ? ($c->nro_factura ?: 'F-'.str_pad((string) $c->id, 6, '0', STR_PAD_LEFT))
            : ($c->nro_recibo ?: 'R-'.Carbon::parse($c->fecha_cobro)->format('Y').'-'.str_pad((string) $c->id, 5, '0', STR_PAD_LEFT));
    }

    public function datos(Cobranza $c, string $tipo): array
    {
        $c->loadMissing(['contrato.empresa', 'contrato.edificio', 'cuota', 'cuentaBancaria.banco']);
        $empresa = $c->contrato?->empresa;
        $edificio = $c->contrato?->edificio;
        $u = (new UbicacionResolver)->de($empresa ?? $edificio);
        $monto = $tipo === 'recibo' && (float) $c->cobrado_bs > 0 ? (float) $c->cobrado_bs : (float) $c->monto_facturado;

        $detalle = $c->concepto.($c->cuota ? " (cuota {$c->cuota->nro_cuota})" : '');

        return [
            'modelo' => false, 'emisor' => config('sofia.emisor'), 'numero' => $this->numero($c, $tipo),
            'fecha' => Carbon::parse($tipo === 'factura' && $c->fecha_facturacion ? $c->fecha_facturacion : $c->fecha_cobro)->format('d/m/Y'),
            'cliente' => [
                'nombre' => $c->factura_a_nombre ?: ($empresa?->denominacion ?? '—'), 'nit' => $c->nit_facturado ?: ($empresa?->nit ?? ''),
                'edificio' => $edificio?->denominacion, 'direccion' => collect([$u['via'], $u['zona'], $u['municipio'], $u['departamento'], $u['pais']])->filter()->implode(', '),
            ],
            'contrato' => $c->contrato?->codigo_contrato,
            'items' => [['codigo' => $c->cuota ? "C{$c->cuota->nro_cuota}" : '—', 'cantidad' => 1, 'unidad' => 'Servicio', 'descripcion' => $detalle, 'precio' => $monto, 'subtotal' => $monto]],
            'total' => $monto, 'total_letras' => NumeroLetras::moneda($monto),
            'pago' => [
                'metodo' => $c->comprobante_deposito ? 'Transferencia / depósito bancario' : 'Pago registrado',
                'detalle' => collect([$c->comprobante_deposito ? "Ref. {$c->comprobante_deposito}" : null, $c->cuentaBancaria ? "{$c->cuentaBancaria->banco?->nombre} {$c->cuentaBancaria->numero_cuenta}" : null])->filter()->implode(' · '),
            ],
        ];
    }

    private function datosModelo(string $tipo): array
    {
        $monto = 3000.0;

        return [
            'modelo' => true, 'emisor' => config('sofia.emisor'), 'numero' => $tipo === 'factura' ? 'F-000123' : 'R-'.date('Y').'-00045', 'fecha' => now()->format('d/m/Y'),
            'cliente' => ['nombre' => 'CLIENTE DE EJEMPLO S.R.L.', 'nit' => '1234567019', 'edificio' => 'EDIFICIO EJEMPLO CENTRAL', 'direccion' => 'Av. 6 de Agosto, Sopocachi, La Paz, Bolivia'],
            'contrato' => 'C-EJEMPLO-001',
            'items' => [['codigo' => 'C2', 'cantidad' => 1, 'unidad' => 'Servicio', 'descripcion' => 'Mantenimiento trimestral de ascensores (cuota 2)', 'precio' => $monto, 'subtotal' => $monto]],
            'total' => $monto, 'total_letras' => NumeroLetras::moneda($monto),
            'pago' => ['metodo' => 'Transferencia bancaria', 'detalle' => 'Ref. TRX-00012345 · Cuenta de pruebas'],
        ];
    }

    // ------------------------------------------------------------------ informe de visita

    public function informeVisita(VisitaMantenimiento $v): DomPdf
    {
        $v->loadMissing(['contrato.empresa', 'contrato.edificio.equipos', 'tecnico']);
        $edificio = $v->contrato->edificio;
        $u = (new UbicacionResolver)->de($edificio);

        return Pdf::loadView('comprobantes.informe-visita', ['d' => [
            'emisor' => config('sofia.emisor'), 'tipo' => $v->tipo === 'PREVENTIVO' ? 'Preventiva' : 'Correctiva', 'nro' => $v->nro_visita,
            'contrato' => $v->contrato->codigo_contrato ?? "#{$v->contrato_id}", 'cliente' => $v->contrato->empresa?->denominacion, 'edificio' => $edificio?->denominacion,
            'ubicacion' => collect([$u['via'], $u['zona'], $u['municipio'], $u['departamento'], $u['pais']])->filter()->implode(', '),
            'programada' => $v->fecha_programada?->format('d/m/Y'), 'realizada' => $v->fecha_realizada?->format('d/m/Y'),
            'tecnico' => $v->tecnico ? trim("{$v->tecnico->nombres} {$v->tecnico->apellidos}") : null,
            'situacion' => config('sofia.situaciones_visita')[$v->situacion] ?? $v->situacion,
            'equipos' => $edificio?->equipos->map(fn ($e) => ['codigo' => $e->codigo, 'descripcion' => $e->descripcion, 'detalle' => trim("{$e->marca} {$e->modelo}")])->all() ?? [],
            'descripcion' => $v->descripcion, 'informe' => $v->informe, 'firma' => $v->firma_cliente, 'firmado_por' => $v->firmado_por,
            'firmado_en' => $v->firmado_en?->format('d/m/Y H:i'), 'calificacion' => $v->calificacion,
        ]])->setPaper('letter');
    }
}
