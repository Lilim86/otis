<?php

namespace App\Services;

use App\Mail\AvisoClienteMail;
use App\Models\Cita;
use App\Models\PedidoCatalogo;
use App\Models\ProductoCatalogo;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/** Registra los pedidos del catálogo: los agenda para el gerente a 3 días hábiles y le avisa. */
class PedidoCatalogoService
{
    public const DIAS_HABILES = 3;

    /** Situación de la cita en la agenda que corresponde a cada situación del pedido. */
    private const SITUACION_CITA = ['PENDIENTE' => 'PROGRAMADA', 'ATENDIDO' => 'ATENDIDA', 'CANCELADO' => 'CANCELADA'];

    public function __construct(private CronogramaService $cronograma) {}

    public function fechaCompromiso(): Carbon
    {
        return $this->cronograma->sumarDiasHabiles(today(), self::DIAS_HABILES);
    }

    /** @param  array{cantidad: int, edificio_id?: ?int, observaciones?: ?string}  $datos */
    public function registrar(User $usuario, ProductoCatalogo $producto, array $datos): PedidoCatalogo
    {
        $pedido = DB::transaction(function () use ($usuario, $producto, $datos) {
            $pedido = PedidoCatalogo::create([
                'producto_id' => $producto->id, 'empresa_id' => $usuario->empresa_id, 'edificio_id' => $datos['edificio_id'] ?? null, 'user_id' => $usuario->id,
                'cantidad' => $datos['cantidad'], 'observaciones' => $datos['observaciones'] ?? null, 'fecha_compromiso' => $this->fechaCompromiso()->toDateString(),
            ]);
            $pedido->update(['cita_id' => $this->agendar($pedido, $usuario, $producto)->id]);

            return $pedido;
        });

        $this->avisarAlGerente($pedido->load(['producto', 'empresa', 'edificio']), $usuario);

        return $pedido;
    }

    /** Lleva a la agenda el cambio de situación del pedido. */
    public function sincronizarAgenda(PedidoCatalogo $pedido): void
    {
        $pedido->cita?->update(['situacion' => self::SITUACION_CITA[$pedido->situacion]]);
    }

    private function agendar(PedidoCatalogo $pedido, User $usuario, ProductoCatalogo $producto): Cita
    {
        $responsable = (string) config('sofia.gerente_general');
        $inicio = $this->horarioLibre($pedido->fecha_compromiso->copy(), $responsable);

        return Cita::create([
            'titulo' => "Pedido {$pedido->codigo}: {$producto->nombre}", 'tipo' => 'PEDIDO', 'responsable' => $responsable,
            'fecha_inicio' => $inicio->toDateTimeString(), 'fecha_fin' => $inicio->copy()->addHour()->toDateTimeString(),
            'con_quien' => $usuario->name ?: $usuario->username, 'empresa_id' => $pedido->empresa_id, 'edificio_id' => $pedido->edificio_id,
            'motivo' => "Cantidad: {$pedido->cantidad}.".($pedido->observaciones ? " {$pedido->observaciones}" : ''), 'situacion' => 'PROGRAMADA',
        ]);
    }

    /** Primera hora de oficina sin otra cita del gerente; si el día está lleno, a primera hora. */
    private function horarioLibre(Carbon $dia, string $responsable): Carbon
    {
        for ($hora = 9; $hora < 18; $hora++) {
            $inicio = $dia->copy()->setTime($hora, 0);
            if (! Cita::cruzadas($responsable, $inicio, $inicio->copy()->addHour())->exists()) {
                return $inicio;
            }
        }

        return $dia->copy()->setTime(9, 0);
    }

    /** El pedido ya quedó en el sistema: si el correo falla o no está configurado, no se pierde. */
    private function avisarAlGerente(PedidoCatalogo $pedido, User $usuario): void
    {
        $correo = config('sofia.gerente_correo');

        if (! $correo) {
            return;
        }

        try {
            Mail::to($correo)->send(new AvisoClienteMail(
                "Nuevo pedido {$pedido->codigo}: {$pedido->producto->nombre}",
                'Nuevo pedido del catálogo',
                [
                    "Cliente: {$pedido->empresa->denominacion}".($pedido->edificio ? " · Edificio: {$pedido->edificio->denominacion}" : ''),
                    "Producto: {$pedido->producto->nombre} · Cantidad: {$pedido->cantidad}",
                    'Solicitado por: '.($usuario->name ?: $usuario->username),
                    'Atender hasta: '.$pedido->fecha_compromiso->format('d/m/Y').' ('.self::DIAS_HABILES.' días hábiles). Ya está en tu agenda.',
                    ...($pedido->observaciones ? ["Observaciones: {$pedido->observaciones}"] : []),
                ],
                rtrim((string) config('sofia.url_sistema'), '/').'/modulos/pedidos-catalogo',
                'Ver pedidos',
            ));
            $pedido->update(['notificado_correo' => true]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
