<?php

namespace App\Http\Controllers\Api;

use App\Models\Emergencia;
use Illuminate\Database\Eloquent\Model;

class EmergenciaController extends CrudController
{
    protected string $modelo = Emergencia::class;

    protected array $con = ['ascensor.zona'];

    protected array $buscar = ['descripcion_fallo'];

    protected array $filtros = ['ascensor_id', 'gravedad', 'situacion'];

    protected array $padres = ['ascensor' => 'ascensor'];

    protected function reglas(?Model $registro): array
    {
        return [
            'ascensor_id' => ['required', $this->existe('ascensores')],
            'gravedad' => ['required', 'in:Leve,Moderada,Crítica'],
            'descripcion_fallo' => ['required', 'string'],
            'situacion' => ['sometimes', 'in:Reportado,En Proceso,Solucionado'],
        ];
    }

    protected function valoresPorDefecto(): array
    {
        return ['situacion' => 'Reportado'];
    }
}
