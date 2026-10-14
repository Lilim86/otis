<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource genérico: devuelve los atributos del modelo y las relaciones cargadas.
 */
class RegistroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $datos = parent::toArray($request);

        if (array_key_exists('estado', $datos)) {
            $datos['estado'] = (bool) $datos['estado'];
        }

        return $datos;
    }
}
