<?php

namespace App\Http\Controllers\Api;

use App\Models\FondoRendir;
use App\Models\RendicionFondo;
use Illuminate\Database\Eloquent\Model;

class RendicionFondoController extends CrudController
{
    protected string $modelo = RendicionFondo::class;

    protected bool $conAprobacion = true;

    protected array $estadosSinEdicion = ['APR', 'ANL'];

    protected array $con = ['fondo'];

    protected array $buscar = ['nro_comprobante', 'concepto'];

    protected array $filtros = ['fondo_id'];

    protected array $fechas = ['fecha'];

    protected array $orden = [['fecha', 'desc']];

    protected array $padres = ['fondo' => 'fondo'];

    protected function reglas(?Model $registro): array
    {
        $fondo = request()->input('fondo_id', $registro?->fondo_id);

        return [
            'fondo_id' => ['required', $this->existe('fondos_rendir'), function ($atributo, $valor, $fallar) {
                // Solo se rinde un fondo ya aprobado (entregado).
                if (FondoRendir::whereKey($valor)->value('estado_registro') !== 'APR') {
                    $fallar('El fondo debe estar aprobado para registrar rendiciones.');
                }
            }],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'nro_comprobante' => ['nullable', 'string', 'max:40'],
            'concepto' => ['required', 'string', 'max:255'],
            'monto_bs' => ['required', 'numeric', 'gt:0', function ($atributo, $valor, $fallar) use ($fondo, $registro) {
                $asignado = (float) FondoRendir::whereKey($fondo)->value('monto_asignado_bs');
                $rendido = (float) RendicionFondo::where('fondo_id', $fondo)->where('estado_registro', '!=', 'ANL')
                    ->when($registro, fn ($q) => $q->whereKeyNot($registro->getKey()))->sum('monto_bs');

                if ($asignado > 0 && $rendido + (float) $valor > $asignado + 0.001) {
                    $fallar('La rendición supera el saldo del fondo (disponible: '.number_format($asignado - $rendido, 2, '.', '').' Bs).');
                }
            }],
        ];
    }
}
