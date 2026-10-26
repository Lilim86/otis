<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Departamento;
use Illuminate\Http\JsonResponse;

/** Listas cerradas del negocio, para llenar combos en el frontend en una sola petición. */
class CatalogoController extends Controller
{
    public function index(): JsonResponse
    {
        $claves = ['estados_registro', 'tipos_documento', 'expedido_en', 'tipos_persona', 'tipos_empresa', 'tipos_edificacion',
            'unidades_medida', 'rotacion', 'tipos_insumo', 'tipos_almacen', 'estados_cuota', 'tipos_ingreso', 'tipos_fondo', 'monedas', 'tipos_cita', 'situaciones_cita', 'situaciones_visita', 'tipos_visita'];

        $datos = [];
        foreach ($claves as $clave) {
            $datos[$clave] = collect(config("sofia.{$clave}"))
                ->map(fn ($etiqueta, $codigo) => ['codigo' => $codigo, 'etiqueta' => $etiqueta])
                ->values();
        }

        $datos['regionales'] = Departamento::orderBy('nombre')->get(['id', 'nombre']);

        return response()->json(['data' => $datos]);
    }
}
