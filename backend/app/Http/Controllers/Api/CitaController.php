<?php

namespace App\Http\Controllers\Api;

use App\Models\Cita;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CitaController extends CrudController
{
    protected string $modelo = Cita::class;

    protected array $con = ['empresa', 'edificio'];

    protected array $buscar = ['titulo', 'con_quien', 'motivo', 'lugar'];

    protected array $filtros = ['tipo', 'situacion', 'responsable', 'empresa_id', 'edificio_id'];

    protected array $fechas = ['fecha_inicio'];

    protected array $orden = [['fecha_inicio', 'asc']];

    /** Citas que agendó Secretaría y el gerente todavía no vio. Las más recientes primero. */
    public function notificaciones(Request $request): JsonResponse
    {
        $this->exigirGerente($request);

        $nuevas = Cita::with(['empresa', 'edificio'])
            ->whereNull('vista_gerente_en')
            ->where('estado', true)
            ->where('tipo', '!=', 'PEDIDO') // los pedidos del catálogo ya avisan por su cuenta
            ->where('situacion', '!=', 'CANCELADA')
            // La agenda guarda la hora local: se compara contra el día de Bolivia, no el del servidor.
            ->whereDate('fecha_fin', '>=', Carbon::now(config('sofia.zona_horaria'))->toDateString())
            ->whereIn('creado_por', User::whereHas('roles', fn ($rol) => $rol->where('name', 'Secretaría'))->select('id'));

        return response()->json(['total' => (clone $nuevas)->count(), 'data' => $nuevas->orderByDesc('id')->limit(5)->get()]);
    }

    public function marcarVista(Request $request, string $id): Response
    {
        $this->exigirGerente($request);
        Cita::findOrFail($id)->forceFill(['vista_gerente_en' => now()])->save();

        return response()->noContent();
    }

    /** Estos avisos son para el gerente: el Super Administrador y el rol Administrador. */
    private function exigirGerente(Request $request): void
    {
        abort_unless($request->user()->id === 1 || $request->user()->hasRole('Administrador'), 403, 'Este aviso es solo para el gerente.');
    }

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
