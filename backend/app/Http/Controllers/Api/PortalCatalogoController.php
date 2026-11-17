<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contrato;
use App\Models\Edificio;
use App\Models\PedidoCatalogo;
use App\Models\ProductoCatalogo;
use App\Services\PedidoCatalogoService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Portal del cliente: catálogo de equipos y pedidos de la propia empresa. */
class PortalCatalogoController extends Controller
{
    public function __construct(private PedidoCatalogoService $pedidos) {}

    public function catalogo(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ProductoCatalogo::where('estado', true)->orderBy('orden')->get(),
            'edificios' => $this->edificios($request)->map->only(['id', 'denominacion'])->values(),
            'dias_habiles' => PedidoCatalogoService::DIAS_HABILES,
            'fecha_estimada' => $this->pedidos->fechaCompromiso()->toDateString(),
        ]);
    }

    public function pedidos(Request $request): JsonResponse
    {
        $pedidos = PedidoCatalogo::with(['producto', 'edificio'])->where('empresa_id', $request->user()->empresa_id)->orderByDesc('id')->get();

        return response()->json(['data' => $pedidos->map(fn (PedidoCatalogo $p) => $this->resumen($p))]);
    }

    public function pedir(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'producto_id' => ['required', 'integer', Rule::exists('productos_catalogo', 'id')->where('estado', true)->whereNull('deleted_at')],
            'cantidad' => ['required', 'integer', 'min:1', 'max:50'],
            'edificio_id' => ['nullable', 'integer', Rule::in($this->edificios($request)->pluck('id')->all())],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        $pedido = $this->pedidos->registrar($request->user(), ProductoCatalogo::findOrFail($datos['producto_id']), $datos);

        return response()->json([
            'message' => 'Tu pedido quedó agendado para el '.$pedido->fecha_compromiso->format('d/m/Y').'. El gerente ya fue notificado.',
            'data' => $this->resumen($pedido),
        ], 201);
    }

    /** @return Collection<int, Edificio> edificios de la empresa o con contrato vigente de ella */
    private function edificios(Request $request): Collection
    {
        $empresaId = $request->user()->empresa_id;
        $conContrato = Contrato::where('empresa_id', $empresaId)->where('estado_registro', '!=', 'ANL')->pluck('edificio_id');

        return Edificio::where(fn ($q) => $q->where('empresa_id', $empresaId)->orWhereIn('id', $conContrato))->orderBy('denominacion')->get();
    }

    /** @return array<string, mixed> */
    private function resumen(PedidoCatalogo $pedido): array
    {
        return [
            'id' => $pedido->id, 'codigo' => $pedido->codigo, 'producto' => $pedido->producto?->nombre, 'edificio' => $pedido->edificio?->denominacion,
            'cantidad' => $pedido->cantidad, 'observaciones' => $pedido->observaciones, 'fecha_pedido' => $pedido->created_at?->toDateString(),
            'fecha_compromiso' => $pedido->fecha_compromiso->toDateString(), 'situacion' => $pedido->situacion,
        ];
    }
}
