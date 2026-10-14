<?php

namespace App\Http\Controllers\Api;

use App\Models\FondoRendir;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Fondos a rendir: cargos de cuenta, fondos para viajes y caja chica. El saldo es monto_asignado_bs - total_rendido_bs. */
class FondoRendirController extends CrudController
{
    protected string $modelo = FondoRendir::class;

    protected bool $conAprobacion = true;

    protected array $estadosSinEdicion = ['APR', 'ANL'];

    protected array $con = ['responsable', 'regional'];

    protected array $buscar = ['codigo', 'concepto'];

    protected array $filtros = ['tipo', 'responsable_id', 'regional_id'];

    protected array $fechas = ['fecha'];

    protected array $orden = [['fecha', 'desc']];

    protected array $sumas = ['total_rendido_bs' => ['rendiciones', 'monto_bs']];

    protected array $dependientes = ['rendiciones' => 'rendiciones'];

    protected array $padres = ['responsable' => 'responsable', 'regional' => 'regional'];

    protected function reglas(?Model $registro): array
    {
        return [
            'tipo' => ['required', Rule::in(array_keys(config('sofia.tipos_fondo')))],
            'codigo' => ['required', 'string', 'max:30', $this->unico('fondos_rendir', 'codigo', $registro)],
            'responsable_id' => ['required', $this->existe('personas')],
            'regional_id' => ['nullable', $this->existe('departamentos')],
            'fecha' => ['required', 'date'],
            'monto_asignado_bs' => ['required', 'numeric', 'gt:0'],
            'concepto' => ['nullable', 'string', 'max:255'],
        ];
    }
}
