<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReporteController extends Controller
{
    /** Kardex de inventario en PDF: ahora es el reporte combinado "kardex" (grupo, almacén, regional y país). */
    public function kardexPdf(Request $request): Response
    {
        return app(ReporteCombinadoController::class)->kardex($request);
    }
}
