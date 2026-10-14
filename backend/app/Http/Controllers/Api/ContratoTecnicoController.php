<?php

namespace App\Http\Controllers\Api;

use App\Models\ContratoTecnico;
use Illuminate\Database\Eloquent\Model;

class ContratoTecnicoController extends CrudController
{
    protected string $modelo = ContratoTecnico::class;

    protected bool $conAprobacion = true;

    protected array $con = ['personal'];

    protected array $buscar = ['objeto'];

    protected array $filtros = ['personal_id'];

    protected array $fechas = ['fecha_inicio', 'fecha_fin'];

    protected array $orden = [['fecha_inicio', 'desc']];

    protected array $padres = ['personal' => 'personal'];

    protected function reglas(?Model $registro): array
    {
        $personal = request()->input('personal_id', $registro?->personal_id);

        return [
            'personal_id' => ['required', $this->existe('personal')],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => [
                'required', 'date', 'after_or_equal:fecha_inicio',
                // Un técnico no puede tener dos contratos vigentes que se superpongan.
                function ($atributo, $valor, $fallar) use ($personal, $registro) {
                    $inicio = request()->input('fecha_inicio', $registro?->fecha_inicio);
                    $choca = ContratoTecnico::where('personal_id', $personal)->where('estado_registro', '!=', 'ANL')
                        ->when($registro, fn ($q) => $q->whereKeyNot($registro->getKey()))
                        ->whereDate('fecha_inicio', '<=', $valor)->whereDate('fecha_fin', '>=', $inicio)->exists();
                    if ($choca) {
                        $fallar('El técnico ya tiene un contrato en esas fechas.');
                    }
                },
            ],
            'monto_mensual_bs' => ['required', 'numeric', 'gt:0'],
            'objeto' => ['required', 'string', 'max:255'],
        ];
    }
}
