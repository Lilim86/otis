<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegistroResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * CRUD base de la API. Cada módulo solo declara su modelo, sus reglas de
 * validación y, opcionalmente, relaciones, búsqueda, filtros, orden y
 * dependencias entre registros.
 *
 * Listado (index y papelera): ?q=texto, ?estado=1, ?<filtro>=valor,
 * ?<fecha>_desde= / ?<fecha>_hasta=, ?creado_por=, ?creado_desde= / ?creado_hasta=,
 * ?ordenar_por=&direccion=, ?all=1 (sin paginar) y ?per_page=.
 */
abstract class CrudController extends Controller
{
    /** @var class-string<Model> */
    protected string $modelo;

    /** @var class-string<JsonResource> */
    protected string $resourceClase = RegistroResource::class;

    /** Relaciones a cargar en las respuestas. */
    protected array $con = [];

    /** Columnas de texto sobre las que busca ?q=. */
    protected array $buscar = [];

    /** Columnas filtrables con ?columna=valor (además de estado). */
    protected array $filtros = [];

    /** Columnas de fecha filtrables con ?columna_desde= y ?columna_hasta=. */
    protected array $fechas = [];

    /** Orden del listado: [[columna, dirección], ...]. */
    protected array $orden = [['id', 'desc']];

    /** Relaciones hasMany que impiden eliminar: [relación => etiqueta]. */
    protected array $dependientes = [];

    /** Relaciones belongsTo que deben estar vigentes para restaurar: [relación => etiqueta]. */
    protected array $padres = [];

    /** true si el módulo usa el flujo REG -> APR / ANL del SOFIA (columna estado_registro). */
    protected bool $conAprobacion = false;

    /** Estados de registro en los que ya no se permite modificar. */
    protected array $estadosSinEdicion = ['ANL'];

    /** Sumas calculadas de relaciones: [alias => [relación, columna]]. */
    protected array $sumas = [];

    /** false si `estado` tiene otro significado en el módulo (p. ej. pagado en cobranzas). */
    protected bool $usaEstado = true;

    /** Reglas de validación del módulo. $registro es null al crear. */
    abstract protected function reglas(?Model $registro): array;

    /** Valores que se aplican al crear si el cliente no los envía. */
    protected function valoresPorDefecto(): array
    {
        return [];
    }

    /** Quita o transforma datos validados antes de guardarlos en el modelo. */
    protected function datosParaGuardar(array $datos): array
    {
        return $datos;
    }

    /** Se ejecuta tras crear o modificar (relaciones hijas, etc.). */
    protected function despuesDeGuardar(Model $registro, Request $request): void {}

    // ---------------------------------------------------------------- listados

    public function index(Request $request)
    {
        return $this->listar($request, false);
    }

    /** Registros eliminados lógicamente. */
    public function papelera(Request $request)
    {
        return $this->listar($request, true);
    }

    private function listar(Request $request, bool $papelera)
    {
        $this->validarConsulta($request);

        $consulta = $this->modelo::query()->with($this->con);
        foreach ($this->sumas as $alias => [$relacion, $columna]) {
            $consulta->withSum("{$relacion} as {$alias}", $columna);
        }

        if ($papelera) {
            $consulta->onlyTrashed();
        }

        $this->aplicarFiltros($consulta, $request);
        $this->aplicarOrden($consulta, $request, $papelera);

        $resultado = $request->boolean('all')
            ? $consulta->get()
            : $consulta->paginate($request->integer('per_page', 15));

        return $this->resourceClase::collection($resultado);
    }

    private function validarConsulta(Request $request): void
    {
        $reglas = [
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'boolean'],
            'all' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'ordenar_por' => ['nullable', Rule::in($this->columnasOrdenables())],
            'direccion' => ['nullable', 'in:asc,desc'],
            'creado_por' => ['nullable', 'integer'],
            'creado_desde' => ['nullable', 'date'],
            'creado_hasta' => ['nullable', 'date', 'after_or_equal:creado_desde'],
        ];

        if ($this->conAprobacion) {
            $reglas['estado_registro'] = ['nullable', Rule::in(array_keys(config('sofia.estados_registro')))];
        }

        foreach ($this->filtros as $columna) {
            $reglas[$columna] = ['nullable', 'string', 'max:100'];
        }

        foreach ($this->fechas as $columna) {
            $reglas["{$columna}_desde"] = ['nullable', 'date'];
            $reglas["{$columna}_hasta"] = ['nullable', 'date', "after_or_equal:{$columna}_desde"];
        }

        $request->validate($reglas);
    }

    private function aplicarFiltros(Builder $consulta, Request $request): void
    {
        foreach ($this->filtros as $columna) {
            if ($request->filled($columna)) {
                $consulta->where($columna, $request->query($columna));
            }
        }

        if ($request->filled('estado')) {
            $consulta->where('estado', $request->boolean('estado'));
        }

        if ($this->conAprobacion && $request->filled('estado_registro')) {
            $consulta->where('estado_registro', $request->query('estado_registro'));
        }

        if ($request->filled('creado_por')) {
            $consulta->where('creado_por', $request->integer('creado_por'));
        }

        $rangos = ['created_at' => 'creado'] + array_combine($this->fechas, $this->fechas);
        foreach ($rangos as $columna => $prefijo) {
            if ($request->filled("{$prefijo}_desde")) {
                $consulta->whereDate($columna, '>=', $request->query("{$prefijo}_desde"));
            }
            if ($request->filled("{$prefijo}_hasta")) {
                $consulta->whereDate($columna, '<=', $request->query("{$prefijo}_hasta"));
            }
        }

        if ($request->filled('q') && $this->buscar) {
            $like = '%'.mb_strtolower($request->query('q')).'%';
            $consulta->where(function ($sub) use ($like) {
                foreach ($this->buscar as $columna) {
                    $sub->orWhereRaw("LOWER({$columna}) LIKE ?", [$like]);
                }
            });
        }
    }

    private function aplicarOrden(Builder $consulta, Request $request, bool $papelera): void
    {
        if ($request->filled('ordenar_por')) {
            $consulta->orderBy($request->query('ordenar_por'), $request->query('direccion', 'asc'));

            return;
        }

        $orden = $papelera ? [['deleted_at', 'desc']] : $this->orden;
        foreach ($orden as [$columna, $direccion]) {
            $consulta->orderBy($columna, $direccion);
        }
    }

    /** Columnas por las que se permite ordenar (lista blanca). */
    private function columnasOrdenables(): array
    {
        return array_unique(array_merge(
            ['id', 'estado', 'created_at', 'updated_at', 'deleted_at'],
            array_keys($this->reglas(null)),
        ));
    }

    // -------------------------------------------------------------------- CRUD

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate($this->reglasCompletas(null));

        // Registro e hijos (detalles) se guardan juntos: si algo falla, no queda nada a medias.
        $registro = DB::transaction(function () use ($datos, $request) {
            $registro = $this->modelo::create($this->datosParaGuardar($datos) + $this->valoresPorDefecto());
            $this->despuesDeGuardar($registro, $request);

            return $registro;
        });
        $registro->refresh(); // trae los valores por defecto asignados por la base de datos

        return $this->responder($registro)->setStatusCode(201);
    }

    public function show(string $id)
    {
        return $this->recurso($this->buscarRegistro($id));
    }

    public function update(Request $request, string $id)
    {
        $registro = $this->buscarRegistro($id);
        $reglas = $this->reglasCompletas($registro);

        // PATCH permite enviar solo los campos a modificar.
        if ($request->isMethod('PATCH')) {
            $reglas = array_map(
                fn ($regla) => array_merge(['sometimes'], is_array($regla) ? $regla : explode('|', $regla)),
                $reglas
            );
        }

        if ($this->conAprobacion && in_array($registro->estado_registro, $this->estadosSinEdicion, true)) {
            abort(409, 'El registro está '.config('sofia.estados_registro.'.$registro->estado_registro, $registro->estado_registro).' y ya no se puede modificar.');
        }

        $datos = $this->datosParaGuardar($request->validate($reglas));
        DB::transaction(function () use ($registro, $datos, $request) {
            $registro->update($datos);
            $this->despuesDeGuardar($registro, $request);
        });

        return $this->recurso($registro);
    }

    /** Eliminación lógica: el registro pasa a la papelera. */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $registro = $this->buscarRegistro($id);

        if ($rechazo = $this->impedirEliminacion($registro, $request)) {
            return $rechazo;
        }

        // Guarda quién lo eliminó (el borrado suave no dispara la auditoría de actualización).
        $registro->actualizado_por = $request->user()?->id;
        $registro->saveQuietly();
        $registro->delete();

        return response()->json(null, 204);
    }

    // --------------------------------------------------------- baja / alta / papelera

    /** Dar de baja: desactiva el registro sin eliminarlo. */
    public function baja(string $id)
    {
        return $this->cambiarEstado($id, false);
    }

    /** Dar de alta: vuelve a activar un registro dado de baja. */
    public function alta(string $id)
    {
        return $this->cambiarEstado($id, true);
    }

    /** Aprobar: REG -> APR. */
    public function aprobar(string $id)
    {
        $this->exigirAprobacion();
        $registro = $this->buscarRegistro($id);
        $registro->aprobar();

        return $this->recurso($registro);
    }

    /** Anular el registro (ANL). Distinto de eliminar: no va a la papelera, queda anulado y visible. */
    public function anular(string $id)
    {
        $this->exigirAprobacion();
        $registro = $this->buscarRegistro($id);
        $registro->anularRegistro();

        return $this->recurso($registro);
    }

    private function exigirAprobacion(): void
    {
        abort_unless($this->conAprobacion, 404);
    }

    /** Saca el registro de la papelera. */
    public function restaurar(string $id)
    {
        $registro = $this->modelo::onlyTrashed()->find($id);

        if (! $registro) {
            return response()->json(['message' => 'El registro no está en la papelera.'], 404);
        }

        foreach ($this->padres as $relacion => $etiqueta) {
            $padre = $registro->{$relacion}()->withTrashed()->first();
            if ($padre?->trashed()) {
                return response()->json([
                    'message' => "No se puede restaurar: su {$etiqueta} está en la papelera. Restáuralo primero.",
                ], 409);
            }
        }

        $registro->restore();

        return $this->recurso($registro);
    }

    private function cambiarEstado(string $id, bool $estado)
    {
        if (! $this->usaEstado) {
            return response()->json(['message' => 'Este módulo no maneja alta y baja.'], 422);
        }

        $registro = $this->buscarRegistro($id);
        $registro->update(['estado' => $estado]);

        return $this->recurso($registro);
    }

    // ------------------------------------------------------------------- ayudas

    /** Devuelve una respuesta de rechazo si el registro no puede eliminarse; si puede, null. */
    protected function impedirEliminacion(Model $registro, Request $request): ?JsonResponse
    {
        foreach ($this->dependientes as $relacion => $etiqueta) {
            if ($registro->{$relacion}()->exists()) {
                return response()->json([
                    'message' => "No se puede eliminar: aún tiene registros en {$etiqueta}.",
                ], 409);
            }
        }

        return null;
    }

    protected function buscarRegistro(string $id): Model
    {
        return $this->modelo::findOrFail($id);
    }

    protected function recurso(Model $registro): JsonResource
    {
        $registro->load($this->con);
        foreach ($this->sumas as $alias => [$relacion, $columna]) {
            $registro->loadSum("{$relacion} as {$alias}", $columna);
        }

        return new $this->resourceClase($registro);
    }

    protected function responder(Model $registro): JsonResponse
    {
        return $this->recurso($registro)->response();
    }

    private function reglasCompletas(?Model $registro): array
    {
        return $this->reglas($registro) + ['estado' => ['sometimes', 'boolean']];
    }

    /** Regla unique que ignora al propio registro al editar. */
    protected function unico(string $tabla, string $columna, ?Model $registro): Unique
    {
        return Rule::unique($tabla, $columna)->ignore($registro?->getKey());
    }

    /** Regla exists que descarta registros eliminados (en la papelera). */
    protected function existe(string $tabla): Exists
    {
        return Rule::exists($tabla, 'id')->whereNull('deleted_at');
    }
}
