<?php

namespace App\Services;

/**
 * Cálculo de precios de venta a partir del precio de compra FOB (botón "Cálculos" del SOFIA).
 * Los factores están en config/sofia.php y deben confirmarse con la empresa.
 */
class PreciosEquipoService
{
    /** @return array{precio_compra_fob_bs: float, precio_compra_fob_usd: float, precio_venta_base_bs: float, precio_venta_base_usd: float, precio_venta_cliente_bs: float, precio_venta_cliente_usd: float} */
    public function calcular(?float $fobBs, ?float $fobUsd): array
    {
        $tipoCambio = (float) config('sofia.tipo_cambio_usd');

        $fobBs ??= round(($fobUsd ?? 0) * $tipoCambio, 2);
        $fobUsd ??= round($fobBs / $tipoCambio, 2);

        $base = (float) config('sofia.factor_venta_base');
        $cliente = (float) config('sofia.factor_venta_cliente');

        return [
            'precio_compra_fob_bs' => round($fobBs, 2),
            'precio_compra_fob_usd' => round($fobUsd, 2),
            'precio_venta_base_bs' => round($fobBs * $base, 2),
            'precio_venta_base_usd' => round($fobUsd * $base, 2),
            'precio_venta_cliente_bs' => round($fobBs * $cliente, 2),
            'precio_venta_cliente_usd' => round($fobUsd * $cliente, 2),
        ];
    }
}
