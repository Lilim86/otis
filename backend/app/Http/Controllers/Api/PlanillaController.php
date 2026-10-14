<?php

namespace App\Http\Controllers\Api;

use App\Models\Planilla;
use App\Services\PlanillaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanillaController extends CrudController
{
    protected string $modelo = Planilla::class;

    protected bool $conAprobacion = true;

    protected array $estadosSinEdicion = ['APR', 'ANL'];

    protected array $con = ['regional', 'detalles.personal'];

    protected array $buscar = ['periodo', 'observacion'];

    protected array $filtros = ['periodo', 'tipo', 'regional_id'];

    protected array $orden = [['periodo', 'desc']];

    protected array $padres = ['regional' => 'regional'];

    protected function reglas(?Model $registro): array
    {
        $regional = request()->input('regional_id', $registro?->regional_id);

        return [
            'periodo' => [
                'required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
                // Una sola planilla vigente por período y regional.
                Rule::unique('planillas', 'periodo')->where('tipo', request()->input('tipo', 'SUELDOS'))->where('regional_id', $regional)
                    ->where(fn ($q) => $q->where('estado_registro', '!=', 'ANL'))->whereNull('deleted_at')->ignore($registro?->getKey()),
            ],
            'tipo' => ['sometimes', Rule::in(['SUELDOS'])],
            'regional_id' => ['nullable', $this->existe('departamentos')],
            'observacion' => ['nullable', 'string'],
            'detalles' => ['nullable', 'array'],
            'detalles.*.personal_id' => ['required', 'distinct', $this->existe('personal')],
            'detalles.*.haber_basico_bs' => ['required', 'numeric', 'gt:0'],
            'detalles.*.descuentos_bs' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function datosParaGuardar(array $datos): array
    {
        unset($datos['detalles']);

        return $datos;
    }

    protected function despuesDeGuardar(Model $registro, Request $request): void
    {
        if ($request->has('detalles')) {
            app(PlanillaService::class)->guardarDetalles($registro, $request->input('detalles', []));
        }
    }

    public function aprobar(string $id)
    {
        $planilla = $this->buscarRegistro($id);

        abort_unless($planilla->detalles()->exists(), 422, 'La planilla no tiene personal para pagar.');
        $planilla->aprobar();

        return $this->recurso($planilla);
    }
}
