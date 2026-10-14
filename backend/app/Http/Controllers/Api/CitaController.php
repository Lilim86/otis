<?php

namespace App\Http\Controllers\Api;

use App\Models\Cita;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class CitaController extends CrudController
{
    protected string $modelo = Cita::class;

    protected array $con = ['empresa', 'edificio'];

    protected array $buscar = ['titulo', 'con_quien', 'motivo', 'lugar'];

    protected array $filtros = ['tipo', 'situacion', 'responsable', 'empresa_id', 'edificio_id'];

    protected array $fechas = ['fecha_inicio'];

    protected array $orden = [['fecha_inicio', 'asc']];

    protected function valoresPorDefecto(): array
    {
        return ['responsable' => config('sofia.gerente_general')];
    }

    protected function reglas(?Model $registro): array
    {
        $presencia = $registro ? ['sometimes', 'required'] : ['required'];
        $id = $registro?->id;

        return [
            'titulo' => [...$presencia, 'string', 'max:255'],
            'tipo' => ['sometimes', Rule::in(array_keys(config('sofia.tipos_cita')))],
            'responsable' => ['sometimes', 'string', 'max:255'],
            'fecha_inicio' => [...$presencia, 'date'],
            'fecha_fin' => [...$presencia, 'date', 'after:fecha_inicio', function ($atributo, $valor, $fallar) use ($registro, $id) {
                $inicio = request('fecha_inicio', $registro?->fecha_inicio);
                $responsable = request('responsable', $registro?->responsable ?? config('sofia.gerente_general'));
                $situacion = request('situacion', $registro?->situacion ?? 'PROGRAMADA');

                if ($inicio && $situacion !== 'CANCELADA' && Cita::cruzadas($responsable, $inicio, $valor, $id)->exists()) {
                    $fallar('El horario se cruza con otra cita de '.$responsable.'.');
                }
            }],
            'lugar' => ['nullable', 'string', 'max:255'],
            'con_quien' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'empresa_id' => ['nullable', Rule::exists('empresas', 'id')->whereNull('deleted_at')],
            'edificio_id' => ['nullable', Rule::exists('edificios', 'id')->whereNull('deleted_at')],
            'motivo' => ['nullable', 'string', 'max:2000'],
            'situacion' => ['sometimes', Rule::in(array_keys(config('sofia.situaciones_cita')))],
        ];
    }
}
