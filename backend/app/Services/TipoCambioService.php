<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TipoCambioService
{
    private const URL = 'https://open.er-api.com/v6/latest/USD';

    /**
     * Bolivianos por dólar, o null si el servicio externo no responde.
     * Se guarda en caché una hora para no consultar la API en cada petición.
     */
    public function bolivianosPorDolar(): ?float
    {
        return Cache::remember('tipo_cambio_bob', now()->addHour(), function () {
            try {
                $respuesta = Http::timeout(5)->get(self::URL);

                return $respuesta->successful()
                    ? (float) ($respuesta->json('rates.BOB') ?? 6.96)
                    : null;
            } catch (\Throwable) {
                return null;
            }
        });
    }
}
