<?php

namespace App\Http\Controllers\Api;

use App\Models\Contrato;
use App\Models\VisitaMantenimiento;
use App\Services\ComprobanteService;
use App\Services\CronogramaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cronograma e historial de mantenimiento de todos los contratos (lado de la empresa). */
class VisitaMantenimientoController extends CrudController
{
    protected string $modelo = VisitaMantenimiento::class;

    protected array $con = ['contrato.empresa', 'contrato.edificio', 'tecnico'];

    protected array $buscar = ['descripcion', 'informe'];

    protected array $filtros = ['contrato_id', 'tipo', 'situacion', 'tecnico_id'];

    protected array $fechas = ['fecha_programada', 'fecha_realizada'];

    protected array $orden = [['fecha_programada', 'asc']];

    protected array $padres = ['contrato' => 'contrato'];

    protected function reglas(?Model $registro): array
    {
        $presencia = $registro ? ['sometimes', 'required'] : ['required'];

        return [
            'contrato_id' => [...$presencia, $this->existe('contratos')],
            'tipo' => ['sometimes', Rule::in(['PREVENTIVO', 'CORRECTIVO'])],
            'fecha_programada' => [...$presencia, 'date'],
            'fecha_realizada' => ['nullable', 'date', 'before_or_equal:today'],
            'situacion' => ['sometimes', Rule::in(array_keys(config('sofia.situaciones_visita')))],
            'tecnico_id' => ['nullable', $this->existe('personal')],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'informe' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /** Una visita realizada sin fecha de realización se toma como hecha hoy. */
    protected function datosParaGuardar(array $datos): array
    {
        if (($datos['situacion'] ?? null) === 'REALIZADA' && empty($datos['fecha_realizada'])) {
            $datos['fecha_realizada'] = today()->toDateString();
        }

        return $datos;
    }

    /** Genera el cronograma de 30 días hábiles de un contrato. */
    public function generar(Request $request, CronogramaService $cronograma): JsonResponse
    {
        $datos = $request->validate([
            'contrato_id' => ['required', $this->existe('contratos')],
            'regenerar' => ['nullable', 'boolean'],
            'intervalo_dias_habiles' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $contrato = Contrato::findOrFail($datos['contrato_id']);
        abort_if($contrato->estado_registro === 'ANL', 422, 'El contrato está anulado.');

        $visitas = $cronograma->generar($contrato, (bool) ($datos['regenerar'] ?? false), $datos['intervalo_dias_habiles'] ?? null);

        return response()->json(['data' => $visitas, 'total' => $visitas->count()], 201);
    }

    /** Informe de la visita en PDF (con la firma del cliente si ya la dio). */
    public function informe(string $id, ComprobanteService $comprobantes)
    {
        return $comprobantes->informeVisita(VisitaMantenimiento::findOrFail($id))->stream("Informe_visita_{$id}.pdf");
    }
}
