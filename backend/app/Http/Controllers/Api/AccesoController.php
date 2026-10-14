<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Acceso;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Control de accesos: consulta de la bitácora (solo lectura). */
class AccesoController extends Controller
{
    public function index(Request $request)
    {
        $datos = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'evento' => ['nullable', Rule::in(['LOGIN', 'LOGOUT', 'FALLO', 'TOKEN', 'OTP_SOLICITADO', 'OTP_FALLO'])],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $accesos = Acceso::with('usuario:id,name,username')
            ->when($datos['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($datos['evento'] ?? null, fn ($q, $v) => $q->where('evento', $v))
            ->when($datos['desde'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($datos['hasta'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('id')->paginate($datos['per_page'] ?? 15);

        return response()->json($accesos);
    }
}
