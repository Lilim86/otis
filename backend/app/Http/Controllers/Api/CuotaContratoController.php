<?php

namespace App\Http\Controllers\Api;

use App\Models\CuotaContrato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CuotaContratoController extends CrudController
{
    protected string $modelo = CuotaContrato::class;

    protected array $con = ['contrato.empresa'];

    protected array $buscar = ['concepto', 'certificado'];

    protected array $filtros = ['contrato_id', 'estado_cuota'];

    protected array $fechas = ['fecha_cuota', 'fecha_conformidad'];

    protected array $orden = [['contrato_id', 'asc'], ['nro_cuota', 'asc']];

    protected array $dependientes = ['cobranzas' => 'cobranzas'];

    protected array $padres = ['contrato' => 'contrato'];

    protected function reglas(?Model $registro): array
    {
        $contrato = request()->input('contrato_id', $registro?->contrato_id);

        return [
            'contrato_id' => ['required', $this->existe('contratos')],
            'nro_cuota' => [
                'required', 'integer', 'min:1', 'max:999',
                Rule::unique('cuotas_contrato', 'nro_cuota')->where('contrato_id', $contrato)->whereNull('deleted_at')->ignore($registro?->getKey()),
            ],
            'fecha_cuota' => ['required', 'date'],
            'estado_cuota' => ['sometimes', Rule::in(array_keys(config('sofia.estados_cuota')))],
            'monto_bs' => ['required', 'numeric', 'min:0'],
            'concepto' => ['nullable', 'string', 'max:255'],
            'certificado' => ['nullable', 'string', 'max:255'],
            'fecha_conformidad' => ['nullable', 'date'],
        ];
    }
}
