<?php

namespace App\Models;

/** Ascensor, escalera o elevador de carga que el cliente ve en el catálogo de su portal. */
class ProductoCatalogo extends Auditable
{
    protected $table = 'productos_catalogo';

    protected $fillable = [
        'codigo', 'categoria', 'nombre', 'descripcion', 'especificaciones', 'imagen_url', 'catalogo_url', 'orden', 'estado',
    ];

    protected function casts(): array
    {
        return ['especificaciones' => 'array', 'estado' => 'boolean'];
    }
}
