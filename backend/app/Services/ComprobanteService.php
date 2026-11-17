<?php

namespace App\Services;

use App\Models\Cobranza;
use App\Models\CuotaContrato;
use App\Models\VisitaMantenimiento;
use App\Support\NumeroLetras;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
        // El recibo tiene el tamaño del talonario (media carta apaisada); la factura, carta.
        return Pdf::loadView("comprobantes.{$tipo}", ['d' => $datos])->setPaper($tipo === 'recibo' ? [0, 0, 612, 425] : 'letter', 'portrait');
    }

    public const MESES = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];

    public function numero(Cobranza $c, string $tipo): string
    {
        return $tipo === 'factura'
            ? ($c->nro_factura ?: (string) $c->id)
            : ($c->nro_recibo ?: str_pad((string) $c->id, 8, '0', STR_PAD_LEFT));
    }

    /** Código del cliente que va en la factura y el recibo. */
    public static function codigoCliente(?int $empresaId): ?string
    {
        return $empresaId ? (string) (11000 + $empresaId) : null;
    }

    public function datos(Cobranza $c, string $tipo): array
    {
        $c->loadMissing(['contrato.empresa', 'contrato.edificio', 'cuota', 'cuentaBancaria.banco']);
        $empresa = $c->contrato?->empresa;
        $edificio = $c->contrato?->edificio;
        $u = (new UbicacionResolver)->de($empresa ?? $edificio);
        $monto = $tipo === 'recibo' && (float) $c->cobrado_bs > 0 ? (float) $c->cobrado_bs : (float) $c->monto_facturado;
        // Mes al que corresponde el cobro: el de la cuota; si no tiene, el del cobro.
        $periodo = Carbon::parse($c->cuota?->fecha_cuota ?? $c->fecha_cobro);
        $servicio = $this->tipoServicio($c->concepto);

        $base = [
            'modelo' => false, 'emisor' => config('sofia.emisor'), 'numero' => $this->numero($c, $tipo),
            'cliente' => [
                'nombre' => $c->factura_a_nombre ?: ($empresa?->denominacion ?? '—'), 'nit' => $c->nit_facturado ?: ($empresa?->nit ?? ''),
                'codigo' => self::codigoCliente($empresa?->id),
                'edificio' => $edificio?->denominacion, 'direccion' => collect([$u['via'], $u['zona'], $u['municipio'], $u['departamento'], $u['pais']])->filter()->implode(', '),
            ],
            'contrato' => $c->contrato?->codigo_contrato,
            'total' => $monto, 'total_letras' => NumeroLetras::moneda($monto),
        ];

        if ($tipo === 'factura') {
            $fecha = Carbon::parse($c->fecha_facturacion ?: $c->fecha_cobro)->setTimeFrom($c->created_at ?? now());
            $detalle = match (true) {
                $c->tipo_cobro === 'CONTRATO' => 'Contrato y renovación del servicio de mantenimiento'.($c->contrato?->codigo_contrato ? " {$c->contrato->codigo_contrato}" : ''),
                $servicio === 'MANT' => 'Servicio de mantenimiento integral',
                default => $c->concepto,
            };
            $descripcion = '- '.mb_strtoupper($detalle).' - Mes: '.self::MESES[$periodo->month - 1].' - '.$periodo->year;

            return $base + [
                'fecha' => $fecha->format('d/m/Y H:i:s'),
                'items' => [['codigo' => config('sofia.emisor.codigo_servicio'), 'cantidad' => 1, 'unidad' => 'SERVICIO', 'descripcion' => $descripcion, 'precio' => $monto, 'descuento' => 0, 'subtotal' => $monto]],
                'qr' => $this->qrFactura($base['numero'], $fecha, $monto, $base['cliente']['nit']),
            ];
        }

        $cobradoCuota = $c->cuota ? $c->cuota->cobrado() : $monto;
        $pagoTotal = ! $c->cuota || $cobradoCuota >= (float) $c->cuota->monto_bs;
        $meses = $this->mesesCancelados($c, $periodo, $pagoTotal);

        return $base + [
            'fecha' => Carbon::parse($c->fecha_cobro),
            'suma_letras' => NumeroLetras::moneda($monto, 'Bolivianos'),
            // El cobrador dice el método; en las cobranzas cargadas en la oficina se deduce del depósito.
            'forma' => [
                'efectivo' => $c->metodo_pago ? $c->metodo_pago === 'EFECTIVO' : ! $c->comprobante_deposito && ! $c->cuenta_bancaria_id,
                'cheque' => $c->nro_cheque,
                'banco' => $c->metodo_pago === 'CHEQUE' ? $c->banco_cheque : ($c->cuentaBancaria?->banco?->sigla ?: $c->cuentaBancaria?->banco?->nombre),
                'transferencia' => $c->comprobante_deposito,
            ],
            'pago_total' => $pagoTotal,
            // Un pago por transferencia tiene un concepto genérico: el servicio lo dice la cuota.
            'servicio' => $this->tipoServicio(trim("{$c->concepto} {$c->cuota?->concepto}")),
            'gestion' => $meses->first()->year === $periodo->year ? $periodo->year : "{$meses->first()->year}-{$periodo->year}",
            // "Colocar el Nro. Factura de los meses cancelados": va en la casilla de cada mes.
            'meses' => $meses->mapWithKeys(fn (Carbon $mes) => [$mes->month => $c->nro_factura ?: 'X'])->all(),
        ];
    }

    /**
     * Meses que cancela el cobro: los que van desde la cuota anterior (o el inicio del contrato)
     * hasta el de esta cuota. Un pago parcial o sin cuota solo marca su propio mes.
     *
     * @return Collection<int, Carbon>
     */
    private function mesesCancelados(Cobranza $c, Carbon $periodo, bool $pagoTotal): Collection
    {
        $ultimo = $periodo->copy()->startOfMonth();

        if (! $c->cuota || ! $pagoTotal) {
            return collect([$ultimo]);
        }

        $anterior = CuotaContrato::where('contrato_id', $c->cuota->contrato_id)
            ->whereDate('fecha_cuota', '<', $c->cuota->fecha_cuota->toDateString())->max('fecha_cuota') ?? $c->contrato?->fecha_inicio;
        $mes = $anterior ? Carbon::parse($anterior)->startOfMonth()->addMonth() : $ultimo->copy();
        $mes = $mes->max($ultimo->copy()->subMonths(11))->min($ultimo)->copy();

        $meses = collect();
        for (; $mes->lte($ultimo); $mes = $mes->copy()->addMonth()) {
            $meses->push($mes);
        }

        return $meses;
    }

    /** MANT, REPARACION o MODERNIZACION, según el concepto del cobro. */
    private function tipoServicio(?string $concepto): string
    {
        $c = mb_strtolower((string) $concepto);

        return match (true) {
            str_contains($c, 'moderniz') => 'MODERNIZACION',
            str_contains($c, 'repara') || str_contains($c, 'correctiv') => 'REPARACION',
            default => 'MANT',
        };
    }

    /**
     * QR de la factura como SVG (sin GD). Lleva los datos de la factura en texto; el QR del SIN con el CUF
     * lo genera el sistema de facturación autorizado cuando se conecte.
     */
    private function qrFactura(string $numero, Carbon $fecha, float $monto, ?string $nitCliente): string
    {
        $texto = implode('|', ['NIT '.config('sofia.emisor.nit'), "FACTURA {$numero}", $fecha->format('d/m/Y'), 'Bs '.number_format($monto, 2, '.', ''), 'CLIENTE '.($nitCliente ?: 'S/N')]);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(180, 1), new SvgImageBackEnd)))->writeString($texto);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Documento modelo para ver el diseño. La factura lleva datos de ejemplo; el recibo sale con los
     * campos en blanco, tal como el talonario: los llena Cobranzas según el cliente del edificio.
     */
    private function datosModelo(string $tipo): array
    {
        $emisor = config('sofia.emisor');

        if ($tipo === 'recibo') {
            return [
                'modelo' => true, 'emisor' => $emisor, 'numero' => null, 'fecha' => null,
                'cliente' => ['nombre' => null, 'nit' => null, 'codigo' => null, 'edificio' => null, 'direccion' => null],
                'contrato' => null, 'total' => null, 'total_letras' => null, 'suma_letras' => null,
                'forma' => ['efectivo' => false, 'cheque' => null, 'banco' => null, 'transferencia' => null],
                'pago_total' => null, 'servicio' => null, 'gestion' => null, 'meses' => [],
            ];
        }

        $monto = 3000.0;
        $fecha = now();

        return [
            'modelo' => true, 'emisor' => $emisor, 'numero' => '123', 'fecha' => $fecha->format('d/m/Y H:i:s'),
            'cliente' => ['nombre' => 'CLIENTE DE EJEMPLO S.R.L.', 'nit' => '1234567019', 'codigo' => '11001', 'edificio' => 'EDIFICIO EJEMPLO CENTRAL', 'direccion' => 'Av. 6 de Agosto, Sopocachi, La Paz, Bolivia'],
            'contrato' => 'C-EJEMPLO-001',
            'items' => [['codigo' => $emisor['codigo_servicio'], 'cantidad' => 1, 'unidad' => 'SERVICIO', 'descripcion' => '- SERVICIO DE MANTENIMIENTO INTEGRAL - Mes: '.self::MESES[$fecha->month - 1].' - '.$fecha->year, 'precio' => $monto, 'descuento' => 0, 'subtotal' => $monto]],
            'total' => $monto, 'total_letras' => NumeroLetras::moneda($monto),
            'qr' => $this->qrFactura('123', $fecha, $monto, '1234567019'),
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
