<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function resumen(Request $request, DashboardService $servicio): JsonResponse
    {
        return response()->json(['data' => $servicio->resumen($request->user())]);
    }
}
