<?php

namespace App\Services;

use App\Models\Cobranza;
use App\Models\CuotaContrato;
use App\Models\Edificio;
use App\Models\IngresoTesoreria;
use App\Models\User;
use App\Services\Ingenieria\RutasService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Trabajo del cobrador y su rendición ante Tesorería:
 * ruta por sus edificios -> cobra (efectivo, cheque o transferencia) -> emite factura y recibo ->
 * envía a Tesorería -> Tesorería aprueba (nace el ingreso y la cuota queda cobrada) u observa (el cobrador corrige y reenvía).
 */
class CobradorService
{
    /** Se muestran las cuotas vencidas y las que vencen dentro de estos días. */
    public const DIAS_ANTICIPO = 30;

    /** Situaciones en las que el cobrador todavía puede corregir o borrar su cobro. */
    public const EDITABLES = ['PENDIENTE', 'OBSERVADO'];

    public function __construct(private UbicacionResolver $ubicacion) {}

    // ------------------------------------------------------------------ ruta

    /** Edificios del cobrador con lo que tiene por cobrar, en el orden de la ruta óptima desde la oficina. */
    public function ruta(int $cobradorId): array
    {
        $hasta = today()->addDays(self::DIAS_ANTICIPO);

        $edificios = Edificio::where('cobrador_id', $cobradorId)->where('estado', true)
            ->with(['empresa', 'equipos', 'contactoCobranzas', 'contratos' => fn ($q) => $q->where('estado_registro', '!=', 'ANL')->with([
                'cuotas' => fn ($c) => $c->where('estado_cuota', '!=', 'COBRADA')->whereDate('fecha_cuota', '<=', $hasta)->orderBy('fecha_cuota')
                    ->withSum(['cobranzas as ya_cobrado' => fn ($q) => $q->where('estado_registro', '!=', 'ANL')], 'cobrado_bs'),
            ])])
            ->orderBy('denominacion')->get()
            ->map(fn (Edificio $e) => $this->parada($e));

        $conCobro = $edificios->filter(fn ($e) => $e['por_cobrar'] > 0);
        $ubicados = $conCobro->filter(fn ($e) => $e['latitud'] !== null)->values();
        $puntos = $ubicados->map(fn ($e) => [$e['latitud'], $e['longitud']])->all();
        $orden = collect(RutasService::optimizar($puntos))->map(fn ($i) => $ubicados[$i]);

        // Primero la ruta; luego los que tienen cobro pero no tienen coordenadas; al final los que están al día.
        $paradas = $orden->concat($conCobro->filter(fn ($e) => $e['latitud'] === null))->values()
            ->map(fn ($e, $i) => $e + ['orden' => $i + 1]);
        $km = RutasService::largo($orden->map(fn ($e) => [$e['latitud'], $e['longitud']])->all());

        return [
            'base' => RutasService::base(),
            'paradas' => $paradas,
            'al_dia' => $edificios->filter(fn ($e) => $e['por_cobrar'] <= 0)->values(),
            'resumen' => [
                'edificios' => $edificios->count(),
                'por_visitar' => $paradas->count(),
                'por_cobrar' => round($paradas->sum('por_cobrar'), 2),
                'vencido' => round($paradas->sum('vencido'), 2),
                'km' => round($km, 1),
                'minutos' => RutasService::minutos($km, $orden->count()),
            ],
        ];
    }

    private function parada(Edificio $e): array
    {
        $u = $this->ubicacion->de($e);
        $cuotas = $e->contratos->flatMap(fn ($contrato) => $contrato->cuotas->map(function (CuotaContrato $c) use ($contrato) {
            $saldo = round((float) $c->monto_bs - (float) $c->ya_cobrado, 2);

            return [
                'id' => $c->id, 'nro_cuota' => $c->nro_cuota, 'concepto' => $c->concepto, 'fecha' => $c->fecha_cuota->toDateString(),
                'monto' => (float) $c->monto_bs, 'saldo' => $saldo, 'vencida' => $c->fecha_cuota->lt(today()),
                'contrato' => $contrato->codigo_contrato ?? "#{$contrato->id}", 'tipo_sugerido' => self::tipoSugerido($c->concepto),
            ];
        }))->filter(fn ($c) => $c['saldo'] > 0)->values();
        $contacto = $e->contactoCobranzas;

        return [
            'id' => $e->id, 'edificio' => $e->denominacion, 'cliente' => $e->empresa?->denominacion,
            'nit' => $e->empresa?->nit, 'direccion' => collect([$u['via'], $u['zona'], $u['municipio']])->filter()->implode(', '),
            'referencia' => $e->referencia_cercana,
            'latitud' => $e->latitud !== null ? (float) $e->latitud : null, 'longitud' => $e->longitud !== null ? (float) $e->longitud : null,
            'contacto' => $contacto ? ['nombre' => $contacto->nombre_completo, 'telefono' => $contacto->telefono_celular ?: $contacto->telefono_oficina] : null,
            // El monto de cada edificio depende de sus ascensores: se muestran para que el cobrador lo entienda.
            'equipos' => $e->equipos->map(fn ($q) => trim("{$q->descripcion} ".($q->paradas ? "· {$q->paradas} paradas" : '')))->values(),
            'cuotas' => $cuotas,
            'por_cobrar' => round($cuotas->sum('saldo'), 2),
            'vencido' => round($cuotas->where('vencida', true)->sum('saldo'), 2),
        ];
    }

    /** Si la cuota habla de contrato o renovación, se cobra como tal; si no, es mantenimiento. */
    public static function tipoSugerido(?string $concepto): string
    {
        return preg_match('/contrat|renov/i', (string) $concepto) ? 'CONTRATO' : 'MANTENIMIENTO';
    }

    // ------------------------------------------------------------------ cobros

    /** Cuota cobrable por este cobrador (de uno de sus edificios), con lo que le falta pagar sin contar $sinCobranza. */
    public function cuotaDelCobrador(int $cobradorId, int $cuotaId, ?int $sinCobranza = null): array
    {
        $cuota = CuotaContrato::with('contrato.empresa', 'contrato.edificio')
            ->whereHas('contrato', fn ($q) => $q->where('estado_registro', '!=', 'ANL')->whereHas('edificio', fn ($e) => $e->where('cobrador_id', $cobradorId)))
            ->find($cuotaId);

        if (! $cuota) {
            throw ValidationException::withMessages(['cuota_id' => 'Esa cuota no es de uno de tus edificios.']);
        }
        if ($cuota->estado_cuota === 'COBRADA') {
            throw ValidationException::withMessages(['cuota_id' => 'Esa cuota ya está cobrada.']);
        }

        $cobrado = (float) $cuota->cobranzas()->where('estado_registro', '!=', 'ANL')->when($sinCobranza, fn ($q) => $q->whereKeyNot($sinCobranza))->sum('cobrado_bs');

        return [$cuota, round((float) $cuota->monto_bs - $cobrado, 2)];
    }

    /** Registra lo que el cobrador cobró en el edificio y emite su factura. Queda "por enviar" a Tesorería. */
    public function registrar(User $usuario, array $datos): Cobranza
    {
        return DB::transaction(function () use ($usuario, $datos) {
            [$cuota, $saldo] = $this->cuotaDelCobrador($usuario->persona_id, (int) $datos['cuota_id']);
            $this->exigirSaldo((float) $datos['monto'], $saldo);
            $empresa = $cuota->contrato->empresa;

            $cobranza = new Cobranza($this->campos($datos, $cuota) + [
                'contrato_id' => $cuota->contrato_id,
                'cuota_id' => $cuota->id,
                'cobrador_id' => $usuario->persona_id,
                'nro_factura' => $this->siguienteFactura(),
                'factura_a_nombre' => $datos['factura_a_nombre'] ?? null ?: $empresa?->denominacion,
                'nit_facturado' => $datos['nit_facturado'] ?? null ?: $empresa?->nit,
                'estado' => false, // pagado recién cuando Tesorería lo aprueba
            ]);
            $cobranza->forceFill(['situacion_tesoreria' => 'PENDIENTE'])->save();

            // Con la factura emitida, la cuota deja de estar solo "pendiente".
            if ($cuota->estado_cuota === 'PENDIENTE') {
                $cuota->update(['estado_cuota' => 'FACTURADA']);
            }

            return $cobranza;
        });
    }

    /** Corrige un cobro que todavía no está en Tesorería (o que Tesorería observó). */
    public function actualizar(Cobranza $cobranza, array $datos): Cobranza
    {
        $this->exigirEditable($cobranza);

        return DB::transaction(function () use ($cobranza, $datos) {
            [$cuota, $saldo] = $this->cuotaDelCobrador($cobranza->cobrador_id, $cobranza->cuota_id, $cobranza->id);
            $this->exigirSaldo((float) $datos['monto'], $saldo);

            $cobranza->fill($this->campos($datos, $cuota) + array_filter([
                'factura_a_nombre' => $datos['factura_a_nombre'] ?? null,
                'nit_facturado' => $datos['nit_facturado'] ?? null,
            ]))->save();

            return $cobranza;
        });
    }

    /** Borra un cobro mal cargado. Si la cuota se quedó sin cobros, vuelve a estar pendiente. */
    public function eliminar(Cobranza $cobranza): void
    {
        $this->exigirEditable($cobranza);

        DB::transaction(function () use ($cobranza) {
            $cuota = $cobranza->cuota;
            $cobranza->delete();

            if ($cuota && $cuota->estado_cuota === 'FACTURADA' && ! $cuota->cobranzas()->where('estado_registro', '!=', 'ANL')->exists()) {
                $cuota->update(['estado_cuota' => 'PENDIENTE']);
            }
        });
    }

    /** El cobrador entrega su rendición: los cobros pasan a Tesorería y ya no los puede cambiar. */
    public function enviar(int $cobradorId, array $ids): int
    {
        return Cobranza::where('cobrador_id', $cobradorId)->whereIn('id', $ids)->whereIn('situacion_tesoreria', self::EDITABLES)
            ->get()->each(fn (Cobranza $c) => $c->forceFill(['situacion_tesoreria' => 'ENVIADO', 'enviado_en' => now(), 'observacion_tesoreria' => null])->save())
            ->count();
    }

    private function campos(array $datos, CuotaContrato $cuota): array
    {
        $metodo = $datos['metodo_pago'];
        $tipo = $datos['tipo_cobro'];

        return [
            'tipo_cobro' => $tipo,
            'metodo_pago' => $metodo,
            'concepto' => config("sofia.tipos_cobro.{$tipo}").' - Cuota '.$cuota->nro_cuota.($cuota->concepto ? " ({$cuota->concepto})" : ''),
            'monto_facturado' => $datos['monto'],
            'cobrado_bs' => $datos['monto'],
            'fecha_cobro' => $datos['fecha_cobro'],
            'fecha_facturacion' => $datos['fecha_cobro'],
            'nro_recibo' => $datos['nro_recibo'] ?? null,
            // Solo se guarda lo que corresponde al método elegido.
            'nro_cheque' => $metodo === 'CHEQUE' ? $datos['nro_cheque'] : null,
            'banco_cheque' => $metodo === 'CHEQUE' ? $datos['banco_cheque'] : null,
            'comprobante_deposito' => $metodo === 'TRANSFERENCIA' ? $datos['nro_transferencia'] : null,
            'cuenta_bancaria_id' => $metodo === 'TRANSFERENCIA' ? $datos['cuenta_bancaria_id'] : null,
        ];
    }

    private function exigirSaldo(float $monto, float $saldo): void
    {
        if ($monto > $saldo + 0.001) {
            throw ValidationException::withMessages(['monto' => 'El monto pasa lo que falta pagar de la cuota (Bs '.number_format($saldo, 2, ',', '.').').']);
        }
    }

    private function exigirEditable(Cobranza $cobranza): void
    {
        abort_unless(in_array($cobranza->situacion_tesoreria, self::EDITABLES, true), 409, 'Este cobro ya está en Tesorería y no se puede cambiar.');
    }

    /** Número de la próxima factura: sigue al mayor número que ya existe. */
    private function siguienteFactura(): string
    {
        $mayor = Cobranza::withTrashed()->lockForUpdate()->pluck('nro_factura')
            ->filter(fn ($n) => is_string($n) && ctype_digit($n))->map(fn ($n) => (int) $n)->max() ?? 0;

        return (string) ($mayor + 1);
    }

    // ------------------------------------------------------------------ Tesorería

    /** Cobros de los cobradores en una situación, agrupados por cobrador con sus totales por método de pago. */
    public function bandeja(string $situacion): Collection
    {
        return Cobranza::with(['contrato.empresa', 'contrato.edificio', 'cuota', 'cobrador', 'cuentaBancaria.banco', 'revisor'])
            ->where('situacion_tesoreria', $situacion)
            ->orderBy($situacion === 'APROBADO' ? 'revisado_en' : 'enviado_en', $situacion === 'APROBADO' ? 'desc' : 'asc')
            ->limit(300)->get()
            ->groupBy('cobrador_id')
            ->map(fn (Collection $cobros) => [
                'cobrador' => $cobros->first()->cobrador?->nombre_completo ?? 'Sin cobrador',
                'total' => round($cobros->sum('cobrado_bs'), 2),
                'por_metodo' => collect(config('sofia.metodos_pago'))->map(fn ($etiqueta, $m) => [
                    'metodo' => $m, 'etiqueta' => $etiqueta,
                    'cantidad' => $cobros->where('metodo_pago', $m)->count(),
                    'total' => round($cobros->where('metodo_pago', $m)->sum('cobrado_bs'), 2),
                ])->values(),
                'cobros' => $cobros->values(),
            ])->values();
    }

    /** Tesorería aprueba: nace el ingreso (recibo oficial), la cobranza queda pagada y la cuota, si se cubrió, cobrada. */
    public function aprobar(Cobranza $cobranza, int $cuentaBancariaId, User $revisor): Cobranza
    {
        abort_unless($cobranza->situacion_tesoreria === 'ENVIADO', 409, 'Solo se aprueba un cobro que el cobrador envió a Tesorería.');

        return DB::transaction(function () use ($cobranza, $cuentaBancariaId, $revisor) {
            $nroRecibo = app(ComprobanteService::class)->numero($cobranza, 'recibo');
            if (IngresoTesoreria::withTrashed()->where('nro_recibo', $nroRecibo)->exists()) {
                throw ValidationException::withMessages(['nro_recibo' => "Ya hay un ingreso con el recibo {$nroRecibo}. Observa el cobro para que el cobrador corrija el número."]);
            }

            $ingreso = IngresoTesoreria::create([
                'tipo' => 'RECIBO_OFICIAL', 'nro_recibo' => $nroRecibo, 'fecha' => $cobranza->fecha_cobro,
                'cuenta_bancaria_id' => $cuentaBancariaId, 'cobranza_id' => $cobranza->id, 'monto_bs' => $cobranza->cobrado_bs,
                'concepto' => mb_substr("{$cobranza->concepto} · {$cobranza->contrato?->edificio?->denominacion}", 0, 255),
            ]);
            $ingreso->forceFill(['estado_registro' => 'APR'])->save();

            $cobranza->forceFill([
                'situacion_tesoreria' => 'APROBADO', 'estado_registro' => 'APR', 'estado' => true,
                'cuenta_bancaria_id' => $cobranza->cuenta_bancaria_id ?? $cuentaBancariaId,
                'revisado_en' => now(), 'revisado_por' => $revisor->id, 'observacion_tesoreria' => null,
            ])->save();

            $this->cerrarCuotaSiCorresponde($cobranza->cuota);

            return $cobranza;
        });
    }

    /** Tesorería devuelve el cobro al cobrador con el motivo, para que lo corrija y lo reenvíe. */
    public function observar(Cobranza $cobranza, string $motivo, User $revisor): Cobranza
    {
        abort_unless($cobranza->situacion_tesoreria === 'ENVIADO', 409, 'Solo se observa un cobro que está en Tesorería.');

        $cobranza->forceFill(['situacion_tesoreria' => 'OBSERVADO', 'observacion_tesoreria' => $motivo, 'revisado_en' => now(), 'revisado_por' => $revisor->id])->save();

        return $cobranza;
    }

    /** La cuota queda cobrada solo con lo aprobado: lo cargado en la oficina o lo que Tesorería ya aceptó. */
    private function cerrarCuotaSiCorresponde(?CuotaContrato $cuota): void
    {
        if (! $cuota || $cuota->estado_cuota === 'COBRADA') {
            return;
        }

        $aprobado = (float) $cuota->cobranzas()->where('estado_registro', '!=', 'ANL')
            ->where(fn ($q) => $q->whereNull('situacion_tesoreria')->orWhere('situacion_tesoreria', 'APROBADO'))->sum('cobrado_bs');

        if ($aprobado > 0 && $aprobado >= (float) $cuota->monto_bs - 0.001) {
            $cuota->update(['estado_cuota' => 'COBRADA']);
        }
    }
}
