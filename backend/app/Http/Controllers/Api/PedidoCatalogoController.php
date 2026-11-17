<?php

namespace App\Http\Controllers\Api;

use App\Models\PedidoCatalogo;
use App\Services\PedidoCatalogoService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Pedidos del catálogo vistos por la empresa: los crea el cliente; aquí solo se atienden o cancelan. */
class PedidoCatalogoController extends CrudController
{
    protected string $modelo = PedidoCatalogo::class;

    protected array $con = ['producto', 'empresa', 'edificio', 'cita'];

    protected array $buscar = ['observaciones'];

    protected array $filtros = ['producto_id', 'empresa_id', 'edificio_id', 'situacion'];

    protected array $fechas = ['fecha_compromiso'];

    protected array $orden = [['fecha_compromiso', 'desc']];

    protected function reglas(?Model $registro): array
    {
        return ['situacion' => ['required', Rule::in(array_keys(config('sofia.situaciones_pedido')))]];
    }

    protected function despuesDeGuardar(Model $registro, Request $request): void
    {
        app(PedidoCatalogoService::class)->sincronizarAgenda($registro);
    }
}
