<?php

namespace App\Http\Controllers\Api;

use App\Models\Mantenimiento;
use Illuminate\Database\Eloquent\Model;

class MantenimientoController extends CrudController
{
    protected string $modelo = Mantenimiento::class;

    protected array $con = ['zona'];

    protected array $buscar = ['codigo_ascensor', 'detalles'];

    protected array $filtros = ['zona_id', 'tipo'];

    protected array $orden = [['fecha_programada', 'desc']];

    protected array $fechas = ['fecha_programada'];

    protected array $padres = ['zona' => 'zona'];

    protected function reglas(?Model $registro): array
    {
        return [
            'codigo_ascensor' => ['required', 'string', 'max:255'],
            'zona_id' => ['required', $this->existe('zonas')],
            'tipo' => ['required', 'in:Preventivo,Correctivo'],
            'detalles' => ['required', 'string'],
            'fecha_programada' => ['required', 'date'],
        ];
    }
}
