<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function resumen(DashboardService $servicio): JsonResponse
    {
        return response()->json(['data' => $servicio->resumen()]);
    }
}
