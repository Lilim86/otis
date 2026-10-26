<?php

namespace App\Services;

use App\Mail\AvisoClienteMail;
use App\Models\AvisoEnviado;
use App\Models\CuotaContrato;
use App\Models\PagoCliente;
use App\Models\User;
use App\Models\VisitaMantenimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Avisos por correo a los usuarios cliente de una empresa. Los automáticos (visita próxima, cuota por vencer
 * o vencida) se registran para no repetirse; los de una acción puntual (pago confirmado) salen al momento.
 */
class NotificacionesClienteService
{
    public const DIAS_AVISO_VISITA = 3;

    public const DIAS_AVISO_CUOTA = 5;

    private function portal(): string
    {
        return rtrim((string) config('app.frontend_url', env('FRONTEND_URL_PORTAL', 'http://127.0.0.1:5173')), '/').'/portal';
    }

    /** @return Collection<int, string> correos de los usuarios cliente activos de la empresa */
    private function destinatarios(int $empresaId): Collection
    {
        return User::where('empresa_id', $empresaId)->where('estado', true)->whereNotNull('email')->pluck('email');
    }

    /**
     * Envía los avisos pendientes. Con $simular solo cuenta, sin enviar ni registrar.
     *
     * @return array{visitas: int, cuotas_por_vencer: int, cuotas_vencidas: int}
     */
    public function avisosDelDia(bool $simular = false): array
    {
        $cuenta = ['visitas' => 0, 'cuotas_por_vencer' => 0, 'cuotas_vencidas' => 0];

        $visitas = VisitaMantenimiento::with('contrato.edificio')->whereIn('situacion', ['PROGRAMADA', 'REPROGRAMADA'])
            ->whereDate('fecha_programada', '>=', today())->whereDate('fecha_programada', '<=', today()->addDays(self::DIAS_AVISO_VISITA))->get();

        foreach ($visitas as $v) {
            $dias = (int) today()->diffInDays($v->fecha_programada, false);
            $cuenta['visitas'] += $this->enviarUnaVez('visita', $v->id, $v->contrato->empresa_id, $simular, new AvisoClienteMail(
                'Tu mantenimiento es '.($dias === 0 ? 'hoy' : ($dias === 1 ? 'mañana' : "en {$dias} días")),
                'Mantenimiento programado',
                ["Nuestro técnico visitará {$v->contrato->edificio?->denominacion} el {$v->fecha_programada->format('d/m/Y')}.", 'Te pedimos facilitar el acceso a la sala de máquinas y a los equipos.'],
                $this->portal().'/cronograma', 'Ver mi cronograma',
            ));
        }

        $cuotas = CuotaContrato::with('contrato.edificio')->where('estado_cuota', '!=', 'COBRADA')
            ->whereHas('contrato', fn ($q) => $q->where('estado_registro', '!=', 'ANL'))->whereDate('fecha_cuota', '<=', today()->addDays(self::DIAS_AVISO_CUOTA))->get();

        foreach ($cuotas as $c) {
            $vencida = $c->fecha_cuota->lt(today());
            $clave = $vencida ? 'cuotas_vencidas' : 'cuotas_por_vencer';
            $mail = new AvisoClienteMail(
                $vencida ? "Tu cuota {$c->nro_cuota} está vencida" : "Tu cuota {$c->nro_cuota} vence pronto",
                $vencida ? 'Cuota vencida' : 'Cuota por vencer',
                ["La cuota {$c->nro_cuota} del contrato ".($c->contrato->codigo_contrato ?? "#{$c->contrato_id}").' por Bs '.number_format((float) $c->monto_bs, 2).($vencida ? ' venció el ' : ' vence el ').$c->fecha_cuota->format('d/m/Y').'.', 'Puedes pagarla por transferencia y avisarnos desde tu portal.'],
                $this->portal().'/pagos', 'Ir a pagos',
            );
            $cuenta[$clave] += $this->enviarUnaVez($vencida ? 'cuota_vencida' : 'cuota_por_vencer', $c->id, $c->contrato->empresa_id, $simular, $mail);
        }

        return $cuenta;
    }

    /** Aviso al cliente cuando Financiero confirma o rechaza su pago. */
    public function pagoResuelto(PagoCliente $pago, bool $confirmado): void
    {
        $this->enviar($pago->empresa_id, new AvisoClienteMail(
            $confirmado ? 'Confirmamos tu pago' : 'No pudimos confirmar tu pago',
            $confirmado ? 'Pago confirmado' : 'Pago rechazado',
            $confirmado
                ? ['Confirmamos tu transferencia de Bs '.number_format((float) $pago->monto_bs, 2)." (ref. {$pago->nro_transaccion}). Ya puedes descargar tu recibo desde el portal."]
                : ['No pudimos confirmar tu transferencia ref. '.$pago->nro_transaccion.'.', "Motivo: {$pago->motivo_rechazo}", 'Puedes volver a declararla con los datos correctos.'],
            $this->portal().'/pagos', 'Ver mis pagos',
        ));
    }

    /** Aviso cuando el técnico cierra una visita. */
    public function visitaRealizada(VisitaMantenimiento $v): void
    {
        $v->loadMissing('contrato.edificio');
        $this->enviar($v->contrato->empresa_id, new AvisoClienteMail(
            'Terminamos tu mantenimiento',
            'Visita realizada',
            ["Nuestro técnico terminó la visita en {$v->contrato->edificio?->denominacion}.", 'Ya puedes ver el informe, firmar tu conformidad y calificar el servicio.'],
            $this->portal().'/mantenimiento', 'Ver el informe',
        ));
    }

    private function enviarUnaVez(string $tipo, int $referencia, int $empresaId, bool $simular, AvisoClienteMail $mail): int
    {
        $nuevos = $this->destinatarios($empresaId)->reject(fn ($c) => AvisoEnviado::where(['tipo' => $tipo, 'referencia_id' => $referencia, 'destinatario' => $c])->exists());

        foreach ($nuevos as $correo) {
            if ($simular) {
                continue;
            }
            try {
                Mail::to($correo)->send(clone $mail);
                AvisoEnviado::create(['tipo' => $tipo, 'referencia_id' => $referencia, 'destinatario' => $correo]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $nuevos->isNotEmpty() ? 1 : 0;
    }

    private function enviar(int $empresaId, AvisoClienteMail $mail): void
    {
        foreach ($this->destinatarios($empresaId) as $correo) {
            try {
                Mail::to($correo)->send(clone $mail);
            } catch (\Throwable $e) {
                report($e); // un correo que falla no debe tumbar la operación del personal
            }
        }
    }
}
