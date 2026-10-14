<?php

namespace App\Http\Controllers\Api;

use App\Models\Contrato;
use Illuminate\Database\Eloquent\Model;

class ContratoController extends CrudController
{
    protected string $modelo = Contrato::class;

    protected bool $conAprobacion = true;

    protected array $con = ['empresa', 'edificio', 'regional'];

    protected array $buscar = ['codigo_contrato', 'unidad_oficina'];

    protected array $filtros = ['empresa_id', 'edificio_id', 'regional_id', 'unidad_oficina'];

    protected array $orden = [['fecha_inicio', 'desc']];

    protected array $fechas = ['fecha_inicio', 'fecha_fin'];

    /** Totales como en la grilla "Datos de contratos" del SOFIA. */
    protected array $sumas = [
        'total_cuotas_bs' => ['cuotas', 'monto_bs'],
        'total_facturado_bs' => ['cobranzas', 'monto_facturado'],
        'total_cobrado_bs' => ['cobranzas', 'cobrado_bs'],
    ];

    protected array $dependientes = ['cobranzas' => 'cobranzas', 'cuotas' => 'cuotas'];

    protected array $padres = ['empresa' => 'empresa', 'edificio' => 'edificio', 'regional' => 'regional'];

    protected function reglas(?Model $registro): array
    {
        return [
            'codigo_contrato' => ['nullable', 'string', 'max:30', $this->unico('contratos', 'codigo_contrato', $registro)],
            'empresa_id' => ['required', $this->existe('empresas')],
            'edificio_id' => ['required', $this->existe('edificios')],
            'regional_id' => ['nullable', $this->existe('departamentos')],
            'unidad_oficina' => ['nullable', 'string', 'max:20'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'monto_total' => ['required', 'numeric', 'min:0'],
        ];
    }
}
