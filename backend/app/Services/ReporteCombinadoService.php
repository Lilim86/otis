<?php

namespace App\Services;

use App\Models\Almacen;
use App\Models\Cita;
use App\Models\Cobranza;
use App\Models\Compra;
use App\Models\Contrato;
use App\Models\CuentaBancaria;
use App\Models\CuotaContrato;
use App\Models\Edificio;
use App\Models\Emergencia;
use App\Models\Empresa;
use App\Models\Equipo;
use App\Models\Insumo;
use App\Models\Mantenimiento;
use App\Models\MovimientoAlmacen;
use App\Models\OrdenMantenimiento;
use App\Models\Persona;
use App\Models\Personal;
use App\Models\User;
use App\Models\Zona;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reportes combinados de tablas: cada reporte une su módulo con las tablas relacionadas
 * (cliente, edificio, almacén, grupo, banco, etc.) y con la ubicación completa (país a vía de acceso).
 * Quien puede verlos lo decide el permiso "modulo.ver" de los módulos que cada reporte declara.
 */
class ReporteCombinadoService
{
    /** clave => [título, área del menú, módulos que dan acceso, método que arma las filas]. */
    public const CATALOGO = [
        'ubicaciones' => ['Ubicaciones: país, departamento, provincia, municipio y zona', 'Planificación', ['paises', 'departamentos', 'provincias', 'municipios', 'zonas', 'vias-acceso']],
        'empresas' => ['Empresas con ubicación y contratos', 'Comercial', ['empresas']],
        'edificios' => ['Edificios con empresa, contactos y ubicación', 'Comercial', ['edificios']],
        'contratos' => ['Contratos con cliente, edificio, ubicación y saldo', 'Comercial', ['contratos']],
        'personas' => ['Personas con edificio y ubicación', 'Comercial', ['personas']],
        'equipos' => ['Equipos con grupo, edificio, cliente y ubicación', 'Técnico', ['equipos']],
        'mantenimientos' => ['Mantenimientos con zona y ubicación', 'Técnico', ['mantenimientos']],
        'ordenes_mantenimiento' => ['Órdenes de mantenimiento con repuesto utilizado', 'Técnico', ['ordenes-mantenimiento']],
        'emergencias' => ['Emergencias con ascensor, cliente y ubicación', 'Técnico', ['emergencias']],
        'kardex' => ['Kardex de bienes con grupo y existencias por almacén', 'Almacenes', ['insumos']],
        'inventario_almacen' => ['Inventario por almacén, regional y país', 'Almacenes', ['almacenes', 'movimientos-almacen', 'insumos']],
        'movimientos' => ['Movimientos de almacén con bien, regional y compra', 'Almacenes', ['movimientos-almacen']],
        'compras' => ['Compras con proveedor, regional y almacén', 'Almacenes', ['compras']],
        'cobranzas' => ['Cobranzas con contrato, cliente, edificio, banco y cobrador', 'Financiero', ['cobranzas']],
        'cuotas_pendientes' => ['Cuotas por cobrar con cliente, edificio y ubicación', 'Financiero', ['cobranzas', 'cuotas-contrato']],
        'cuentas_bancarias' => ['Cuentas bancarias con banco y total cobrado', 'Financiero', ['cuentas-bancarias']],
        'personal' => ['Personal con cargo y profesión', 'Recursos Humanos', ['personal']],
        'agenda' => ['Agenda del gerente con empresa, edificio y ubicación', 'Secretaría', ['citas']],
    ];

    private UbicacionResolver $ubicacion;

    /** Reportes que el usuario puede ver, agrupados por área. */
    public function disponibles(User $usuario): array
    {
        $visibles = [];
        foreach (self::CATALOGO as $clave => [$titulo, $area, $modulos]) {
            if ($this->puede($usuario, $clave)) {
                $visibles[$area][$clave] = $titulo;
            }
        }

        return $visibles;
    }

    public function puede(User $usuario, string $clave): bool
    {
        $definicion = self::CATALOGO[$clave] ?? null;

        return $definicion !== null && $usuario->can('modulo', [$definicion[2]]);
    }

    public static function todosLosModulos(): array
    {
        return collect(self::CATALOGO)->flatMap(fn ($d) => $d[2])->unique()->values()->all();
    }

    /**
     * @param  array{desde?: ?string, hasta?: ?string, departamento_id?: ?int, q?: ?string}  $filtros
     * @return array{clave: string, titulo: string, area: string, columnas: array, filas: array, sumas: array}
     */
    public function generar(string $clave, array $filtros = []): array
    {
        if (! isset(self::CATALOGO[$clave])) {
            throw new InvalidArgumentException("Reporte desconocido: {$clave}");
        }

        $this->ubicacion = new UbicacionResolver;
        $metodo = 'reporte'.str_replace('_', '', ucwords($clave, '_'));
        ['columnas' => $columnas, 'filas' => $filas] = $this->$metodo();
        $filas = $this->filtrar(collect($filas), $filtros);

        return [
            'clave' => $clave,
            'titulo' => self::CATALOGO[$clave][0],
            'area' => self::CATALOGO[$clave][1],
            'columnas' => $columnas,
            'filas' => $filas->map(fn ($f) => array_diff_key($f, array_flip(preg_grep('/^_/', array_keys($f)))))->values()->all(),
            'sumas' => $filas->isEmpty() ? [] : $this->sumas($filas, $columnas),
        ];
    }

    // ------------------------------------------------------------------ utilidades

    private function filtrar(Collection $filas, array $f): Collection
    {
        return $filas
            ->when(! empty($f['desde']), fn ($c) => $c->filter(fn ($r) => ! isset($r['_fecha']) || ($r['_fecha'] && $r['_fecha'] >= $f['desde'])))
            ->when(! empty($f['hasta']), fn ($c) => $c->filter(fn ($r) => ! isset($r['_fecha']) || ($r['_fecha'] && $r['_fecha'] <= $f['hasta'])))
            ->when(! empty($f['departamento_id']), fn ($c) => $c->filter(fn ($r) => (int) ($r['_departamento_id'] ?? 0) === (int) $f['departamento_id']))
            ->when(! empty($f['q']), function ($c) use ($f) {
                $buscado = mb_strtolower($f['q']);

                return $c->filter(fn ($r) => collect($r)->reject(fn ($v, $k) => str_starts_with((string) $k, '_'))
                    ->contains(fn ($v) => $v !== null && str_contains(mb_strtolower((string) $v), $buscado)));
            })
            ->values();
    }

    /** Totales al pie: solo columnas monetarias o de conteo (no tiene sentido sumar existencias de bienes distintos). */
    private function sumas(Collection $filas, array $columnas): array
    {
        return collect(array_keys($columnas))->intersect(self::SUMABLES)
            ->mapWithKeys(fn ($k) => [$k => round($filas->sum(fn ($f) => (float) ($f[$k] ?? 0)), 2)])->all();
    }

    private const SUMABLES = [
        'monto_total', 'cobrado_bs', 'saldo_bs', 'monto_bs', 'monto_facturado', 'cobrado_usd', 'total_bs', 'total_usd', 'valor_bs',
        'valor_stock_bs', 'total_cobrado_bs', 'contratos', 'edificios', 'equipos', 'empresas', 'personas', 'vias', 'items', 'cobros',
    ];

    private function ubic(?object $modelo): array
    {
        return $this->ubicacion->de($modelo);
    }

    private function fecha(mixed $valor): ?string
    {
        return $valor ? Carbon::parse($valor)->toDateString() : null;
    }

    private function cols(array $propias, bool $conUbicacion = true): array
    {
        return $propias + ($conUbicacion ? UbicacionResolver::COLUMNAS : []);
    }

    // ------------------------------------------------------------------ Planificación

    private function reporteUbicaciones(): array
    {
        $porZona = fn (string $tabla) => DB::table($tabla)->whereNull('deleted_at')->whereNotNull('zona_id')->groupBy('zona_id')->selectRaw('zona_id, count(*) as n')->pluck('n', 'zona_id');
        [$edificios, $empresas, $personas] = [$porZona('edificios'), $porZona('empresas'), $porZona('personas')];
        $vias = DB::table('via_accesos')->whereNull('deleted_at')->groupBy('zona_id')->selectRaw('zona_id, count(*) as n')->pluck('n', 'zona_id');

        $filas = Zona::orderBy('nombre')->get()->map(fn ($z) => $this->ubic($z) + [
            'vias' => $vias[$z->id] ?? 0, 'edificios' => $edificios[$z->id] ?? 0,
            'empresas' => $empresas[$z->id] ?? 0, 'personas' => $personas[$z->id] ?? 0,
        ])->all();

        return ['columnas' => array_diff_key(UbicacionResolver::COLUMNAS, ['via' => 1]) + ['vias' => 'Vías registradas', 'edificios' => 'Edificios', 'empresas' => 'Empresas', 'personas' => 'Personas'], 'filas' => $filas];
    }

    // ------------------------------------------------------------------ Comercial

    private function reporteEmpresas(): array
    {
        $edificios = Edificio::selectRaw('empresa_id, count(*) as n')->whereNotNull('empresa_id')->groupBy('empresa_id')->pluck('n', 'empresa_id');

        $filas = Empresa::withCount('contratos')->withSum('contratos', 'monto_total')->orderBy('denominacion')->get()->map(fn ($e) => [
            'denominacion' => $e->denominacion, 'nit' => $e->nit, 'tipo_empresa' => $e->tipo_empresa,
        ] + $this->ubic($e) + [
            'telefono' => $e->telefono_celular ?: $e->telefono_fijo, 'correo' => $e->correo_contacto,
            'edificios' => $edificios[$e->id] ?? 0, 'contratos' => $e->contratos_count, 'monto_total' => round((float) $e->contratos_sum_monto_total, 2),
        ])->all();

        return ['columnas' => $this->cols(['denominacion' => 'Empresa', 'nit' => 'NIT', 'tipo_empresa' => 'Tipo'], true) + ['telefono' => 'Teléfono', 'correo' => 'Correo', 'edificios' => 'Edificios', 'contratos' => 'Contratos', 'monto_total' => 'Monto contratos (Bs)'], 'filas' => $filas];
    }

    private function reporteEdificios(): array
    {
        $filas = Edificio::with(['empresa', 'propietario', 'contactoCobranzas', 'contactoTecnico'])->withCount(['contratos', 'equipos'])
            ->orderBy('denominacion')->get()->map(fn ($e) => [
                'codigo' => $e->codigo_edificio, 'denominacion' => $e->denominacion, 'tipo' => $e->tipo_edificacion,
                'empresa' => $e->empresa?->denominacion, 'propietario' => $e->propietario?->nombre_completo,
                'contacto_cobranzas' => $e->contactoCobranzas?->nombre_completo, 'contacto_tecnico' => $e->contactoTecnico?->nombre_completo,
            ] + $this->ubic($e) + ['referencia' => $e->referencia_cercana, 'equipos' => $e->equipos_count, 'contratos' => $e->contratos_count])->all();

        return ['columnas' => ['codigo' => 'Código', 'denominacion' => 'Edificio', 'tipo' => 'Tipo', 'empresa' => 'Empresa', 'propietario' => 'Propietario', 'contacto_cobranzas' => 'Contacto cobranzas', 'contacto_tecnico' => 'Contacto técnico'] + UbicacionResolver::COLUMNAS + ['referencia' => 'Referencia', 'equipos' => 'Equipos', 'contratos' => 'Contratos'], 'filas' => $filas];
    }

    private function reporteContratos(): array
    {
        $filas = Contrato::with(['empresa', 'edificio', 'regional', 'cobranzas' => fn ($q) => $q->where('estado_registro', '!=', 'ANL')])
            ->where('estado_registro', '!=', 'ANL')->orderByDesc('fecha_inicio')->get()->map(function ($c) {
                $cobrado = round((float) $c->cobranzas->sum('cobrado_bs'), 2);

                return [
                    'codigo' => $c->codigo_contrato ?? "#{$c->id}", 'cliente' => $c->empresa?->denominacion, 'nit' => $c->empresa?->nit,
                    'edificio' => $c->edificio?->denominacion,
                ] + $this->ubic($c->edificio) + [
                    'regional' => $c->regional?->nombre, 'fecha_inicio' => $this->fecha($c->fecha_inicio), 'fecha_fin' => $this->fecha($c->fecha_fin),
                    'monto_total' => round((float) $c->monto_total, 2), 'cobrado_bs' => $cobrado, 'saldo_bs' => round((float) $c->monto_total - $cobrado, 2),
                    '_fecha' => $this->fecha($c->fecha_inicio),
                ];
            })->all();

        return ['columnas' => ['codigo' => 'Contrato', 'cliente' => 'Cliente', 'nit' => 'NIT', 'edificio' => 'Edificio'] + UbicacionResolver::COLUMNAS + ['regional' => 'Regional', 'fecha_inicio' => 'Inicio', 'fecha_fin' => 'Fin', 'monto_total' => 'Monto (Bs)', 'cobrado_bs' => 'Cobrado (Bs)', 'saldo_bs' => 'Saldo (Bs)'], 'filas' => $filas];
    }

    private function reportePersonas(): array
    {
        $filas = Persona::with('edificio')->orderBy('primer_apellido')->get()->map(fn ($p) => [
            'nombre' => $p->nombre_completo, 'documento' => trim("{$p->numero_documento} {$p->expedido_en}"), 'tipo' => $p->tipo_persona,
            'celular' => $p->telefono_celular, 'correo' => $p->correo_personal ?: $p->correo_institucional, 'edificio' => $p->edificio?->denominacion,
        ] + $this->ubic($p))->all();

        return ['columnas' => ['nombre' => 'Persona', 'documento' => 'Documento', 'tipo' => 'Tipo', 'celular' => 'Celular', 'correo' => 'Correo', 'edificio' => 'Edificio'] + UbicacionResolver::COLUMNAS, 'filas' => $filas];
    }

    // ------------------------------------------------------------------ Técnico

    private function reporteEquipos(): array
    {
        $filas = Equipo::with(['edificio.empresa', 'grupo'])->orderBy('codigo')->get()->map(fn ($e) => [
            'codigo' => $e->codigo, 'descripcion' => $e->descripcion, 'grupo' => $e->grupo?->nombre, 'marca' => $e->marca, 'modelo' => $e->modelo,
            'edificio' => $e->edificio?->denominacion, 'cliente' => $e->edificio?->empresa?->denominacion,
        ] + $this->ubic($e->edificio) + ['paradas' => $e->paradas, 'horas_servicio' => $e->horas_servicio, 'precio_cliente_bs' => $e->precio_venta_cliente_bs])->all();

        return ['columnas' => ['codigo' => 'Código', 'descripcion' => 'Equipo', 'grupo' => 'Grupo', 'marca' => 'Marca', 'modelo' => 'Modelo', 'edificio' => 'Edificio', 'cliente' => 'Cliente'] + UbicacionResolver::COLUMNAS + ['paradas' => 'Paradas', 'horas_servicio' => 'Horas servicio', 'precio_cliente_bs' => 'Precio cliente (Bs)'], 'filas' => $filas];
    }

    private function reporteMantenimientos(): array
    {
        $filas = Mantenimiento::orderByDesc('fecha_programada')->get()->map(fn ($m) => [
            'ascensor' => $m->codigo_ascensor, 'tipo' => $m->tipo, 'detalles' => $m->detalles, 'fecha_programada' => $this->fecha($m->fecha_programada),
            'situacion' => $m->estado,
        ] + $this->ubic($m) + ['_fecha' => $this->fecha($m->fecha_programada)])->all();

        return ['columnas' => ['ascensor' => 'Ascensor', 'tipo' => 'Tipo', 'detalles' => 'Detalles', 'fecha_programada' => 'Fecha programada', 'situacion' => 'Situación'] + UbicacionResolver::COLUMNAS, 'filas' => $filas];
    }

    private function reporteOrdenesMantenimiento(): array
    {
        $filas = OrdenMantenimiento::with('repuesto')->orderByDesc('fecha_programada')->get()->map(fn ($o) => [
            'orden' => $o->codigo_orden, 'equipo' => $o->equipo_ascensor, 'fecha_programada' => $this->fecha($o->fecha_programada), 'situacion' => $o->situacion,
            'trabajo' => $o->descripcion_trabajo, 'repuesto' => $o->repuesto ? "{$o->repuesto->codigo_parte} - {$o->repuesto->nombre}" : null,
            'ubicacion_almacen' => $o->repuesto?->ubicacion_almacen, 'cantidad' => $o->cantidad_utilizada, '_fecha' => $this->fecha($o->fecha_programada),
        ])->all();

        return ['columnas' => ['orden' => 'Orden', 'equipo' => 'Equipo / ascensor', 'fecha_programada' => 'Fecha programada', 'situacion' => 'Situación', 'trabajo' => 'Trabajo', 'repuesto' => 'Repuesto utilizado', 'ubicacion_almacen' => 'Ubicación en almacén', 'cantidad' => 'Cantidad'], 'filas' => $filas];
    }

    private function reporteEmergencias(): array
    {
        $filas = Emergencia::with('ascensor')->orderByDesc('id')->get()->map(fn ($e) => [
            'ascensor' => $e->ascensor?->codigo, 'cliente' => $e->ascensor?->cliente_edificio, 'modelo' => $e->ascensor?->modelo,
            'gravedad' => $e->gravedad, 'descripcion' => $e->descripcion_fallo, 'situacion' => $e->situacion, 'fecha' => $this->fecha($e->created_at),
        ] + $this->ubic($e->ascensor) + ['_fecha' => $this->fecha($e->created_at)])->all();

        return ['columnas' => ['ascensor' => 'Ascensor', 'cliente' => 'Cliente / edificio', 'modelo' => 'Modelo', 'gravedad' => 'Gravedad', 'descripcion' => 'Falla', 'situacion' => 'Situación', 'fecha' => 'Reportada'] + UbicacionResolver::COLUMNAS, 'filas' => $filas];
    }

    // ------------------------------------------------------------------ Almacenes

    /** Saldo por almacén y bien: [insumo_id][almacen_id] => ['ingresos' =>, 'salidas' =>, 'valor' =>]. */
    private function saldos(): array
    {
        $saldos = [];
        foreach (MovimientoAlmacen::where('estado_registro', '!=', 'ANL')->get(['almacen_id', 'insumo_id', 'tipo', 'cantidad', 'costo_unitario_bs']) as $m) {
            $celda = &$saldos[$m->insumo_id][$m->almacen_id];
            $celda ??= ['ingresos' => 0.0, 'salidas' => 0.0, 'valor' => 0.0];
            if ($m->tipo === 'INGRESO') {
                $celda['ingresos'] += (float) $m->cantidad;
                $celda['valor'] += (float) $m->cantidad * (float) $m->costo_unitario_bs;
            } else {
                $celda['salidas'] += (float) $m->cantidad;
            }
            unset($celda);
        }

        return $saldos;
    }

    private function etiquetaAlmacen($almacen): string
    {
        $u = $this->ubicacion->resolver(['departamento_id' => $almacen->regional_id]);

        return trim("{$almacen->codigo} {$almacen->nombre}".($u['departamento'] ? " ({$u['departamento']}, {$u['pais']})" : ''));
    }

    private function reporteKardex(): array
    {
        $almacenes = Almacen::with('regional')->get()->keyBy('id');
        $saldos = $this->saldos();

        $filas = Insumo::with('grupo.padre')->orderBy('codigo')->get()->map(function ($i) use ($saldos, $almacenes) {
            $existencias = collect($saldos[$i->id] ?? [])->map(fn ($s, $alm) => ['a' => $almacenes[$alm] ?? null, 'saldo' => $s['ingresos'] - $s['salidas']])
                ->filter(fn ($x) => $x['a'] && $x['saldo'] != 0)->map(fn ($x) => $this->etiquetaAlmacen($x['a']).': '.(float) $x['saldo'])->implode('; ');

            return [
                'codigo' => $i->codigo, 'descripcion' => $i->descripcion, 'grupo' => $i->grupo?->nombre, 'grupo_padre' => $i->grupo?->padre?->nombre,
                'tipo' => $i->tipo, 'unidad' => $i->unidad_medida, 'marca' => $i->marca, 'modelo' => $i->modelo,
                'stock_actual' => (float) $i->stock_actual, 'stock_minimo' => $i->stock_minimo, 'bajo_minimo' => (float) $i->stock_actual < (float) $i->stock_minimo ? 'SÍ' : 'No',
                'precio_compra_bs' => $i->precio_compra_bs, 'valor_stock_bs' => round((float) $i->stock_actual * (float) $i->precio_compra_bs, 2),
                'existencias' => $existencias ?: null,
            ];
        })->all();

        return ['columnas' => ['codigo' => 'Código', 'descripcion' => 'Descripción', 'grupo' => 'Grupo', 'grupo_padre' => 'Grupo superior', 'tipo' => 'Tipo', 'unidad' => 'Unidad', 'marca' => 'Marca', 'modelo' => 'Modelo', 'stock_actual' => 'Stock', 'stock_minimo' => 'Mínimo', 'bajo_minimo' => 'Bajo mínimo', 'precio_compra_bs' => 'Precio compra (Bs)', 'valor_stock_bs' => 'Valor stock (Bs)', 'existencias' => 'Existencias por almacén (regional, país)'], 'filas' => $filas];
    }

    private function reporteInventarioAlmacen(): array
    {
        $almacenes = Almacen::with('regional')->get()->keyBy('id');
        $insumos = Insumo::with('grupo')->get()->keyBy('id');
        $filas = [];

        foreach ($this->saldos() as $insumoId => $porAlmacen) {
            foreach ($porAlmacen as $almacenId => $s) {
                $almacen = $almacenes[$almacenId] ?? null;
                $insumo = $insumos[$insumoId] ?? null;
                $u = $this->ubicacion->resolver(['departamento_id' => $almacen?->regional_id]);
                $saldo = round($s['ingresos'] - $s['salidas'], 2);
                $filas[] = [
                    'almacen' => $almacen ? "{$almacen->codigo} {$almacen->nombre}" : null, 'tipo_almacen' => $almacen?->tipo_almacen,
                    'pais' => $u['pais'], 'departamento' => $u['departamento'], 'codigo' => $insumo?->codigo, 'descripcion' => $insumo?->descripcion, 'grupo' => $insumo?->grupo?->nombre,
                    'ingresos' => round($s['ingresos'], 2), 'salidas' => round($s['salidas'], 2), 'saldo' => $saldo,
                    'valor_bs' => $s['ingresos'] > 0 ? round($saldo * ($s['valor'] / $s['ingresos']), 2) : 0, '_departamento_id' => $u['_departamento_id'],
                ];
            }
        }
        usort($filas, fn ($a, $b) => [$a['almacen'], $a['codigo']] <=> [$b['almacen'], $b['codigo']]);

        return ['columnas' => ['almacen' => 'Almacén', 'tipo_almacen' => 'Tipo', 'pais' => 'País', 'departamento' => 'Regional', 'codigo' => 'Código', 'descripcion' => 'Bien', 'grupo' => 'Grupo', 'ingresos' => 'Ingresos', 'salidas' => 'Salidas', 'saldo' => 'Saldo', 'valor_bs' => 'Valor (Bs)'], 'filas' => $filas];
    }

    private function reporteMovimientos(): array
    {
        $filas = MovimientoAlmacen::with(['almacen.regional', 'insumo.grupo', 'compra'])->orderByDesc('fecha')->get()->map(function ($m) {
            $u = $this->ubicacion->resolver(['departamento_id' => $m->almacen?->regional_id]);

            return [
                'fecha' => $this->fecha($m->fecha), 'tipo' => $m->tipo, 'almacen' => $m->almacen ? "{$m->almacen->codigo} {$m->almacen->nombre}" : null,
                'pais' => $u['pais'], 'departamento' => $u['departamento'], 'codigo' => $m->insumo?->codigo, 'bien' => $m->insumo?->descripcion, 'grupo' => $m->insumo?->grupo?->nombre,
                'cantidad' => (float) $m->cantidad, 'costo_unitario_bs' => $m->costo_unitario_bs, 'total_bs' => round((float) $m->cantidad * (float) $m->costo_unitario_bs, 2),
                'compra' => $m->compra?->codigo_compra, 'referencia' => $m->referencia, 'registro' => $m->estado_registro,
                '_fecha' => $this->fecha($m->fecha), '_departamento_id' => $u['_departamento_id'],
            ];
        })->all();

        return ['columnas' => ['fecha' => 'Fecha', 'tipo' => 'Tipo', 'almacen' => 'Almacén', 'pais' => 'País', 'departamento' => 'Regional', 'codigo' => 'Código', 'bien' => 'Bien', 'grupo' => 'Grupo', 'cantidad' => 'Cantidad', 'costo_unitario_bs' => 'Costo unit. (Bs)', 'total_bs' => 'Total (Bs)', 'compra' => 'Compra', 'referencia' => 'Referencia', 'registro' => 'Registro'], 'filas' => $filas];
    }

    private function reporteCompras(): array
    {
        $filas = Compra::with(['proveedor', 'proveedorEmpresa', 'regional', 'almacen', 'detalles'])->orderByDesc('fecha_compra')->get()->map(function ($c) {
            $u = $this->ubicacion->resolver(['departamento_id' => $c->regional_id]);

            return [
                'codigo' => $c->codigo_compra, 'fecha' => $this->fecha($c->fecha_compra),
                'proveedor' => $c->proveedorEmpresa?->denominacion ?? $c->proveedor?->nombre_completo,
                'pais' => $u['pais'], 'departamento' => $u['departamento'], 'almacen' => $c->almacen ? "{$c->almacen->codigo} {$c->almacen->nombre}" : null,
                'items' => $c->detalles->count(), 'total_bs' => $c->total_bs, 'total_usd' => $c->total_usd,
                '_fecha' => $this->fecha($c->fecha_compra), '_departamento_id' => $u['_departamento_id'],
            ];
        })->all();

        return ['columnas' => ['codigo' => 'Compra', 'fecha' => 'Fecha', 'proveedor' => 'Proveedor', 'pais' => 'País', 'departamento' => 'Regional', 'almacen' => 'Almacén', 'items' => 'Ítems', 'total_bs' => 'Total (Bs)', 'total_usd' => 'Total (USD)'], 'filas' => $filas];
    }

    // ------------------------------------------------------------------ Financiero

    private function reporteCobranzas(): array
    {
        $filas = Cobranza::with(['contrato.empresa', 'contrato.edificio', 'cuota', 'cuentaBancaria.banco', 'cobrador'])->orderByDesc('fecha_cobro')->get()->map(fn ($c) => [
            'fecha' => $this->fecha($c->fecha_cobro), 'contrato' => $c->contrato?->codigo_contrato ?? ($c->contrato_id ? "#{$c->contrato_id}" : null),
            'cliente' => $c->contrato?->empresa?->denominacion, 'edificio' => $c->contrato?->edificio?->denominacion,
        ] + $this->ubic($c->contrato?->edificio) + [
            'concepto' => $c->concepto, 'cuota' => $c->cuota?->nro_cuota, 'nro_factura' => $c->nro_factura, 'nro_recibo' => $c->nro_recibo,
            'monto_facturado' => (float) $c->monto_facturado, 'cobrado_bs' => (float) $c->cobrado_bs, 'cobrado_usd' => (float) $c->cobrado_usd,
            'banco' => $c->cuentaBancaria?->banco?->nombre, 'cuenta' => $c->cuentaBancaria?->numero_cuenta, 'cobrador' => $c->cobrador?->nombre_completo,
            'registro' => $c->estado_registro, '_fecha' => $this->fecha($c->fecha_cobro),
        ])->all();

        return ['columnas' => ['fecha' => 'Fecha', 'contrato' => 'Contrato', 'cliente' => 'Cliente', 'edificio' => 'Edificio'] + UbicacionResolver::COLUMNAS + ['concepto' => 'Concepto', 'cuota' => 'Cuota', 'nro_factura' => 'Factura', 'nro_recibo' => 'Recibo', 'monto_facturado' => 'Facturado (Bs)', 'cobrado_bs' => 'Cobrado (Bs)', 'cobrado_usd' => 'Cobrado (USD)', 'banco' => 'Banco', 'cuenta' => 'Cuenta', 'cobrador' => 'Cobrador', 'registro' => 'Registro'], 'filas' => $filas];
    }

    private function reporteCuotasPendientes(): array
    {
        $filas = CuotaContrato::with(['contrato.empresa', 'contrato.edificio'])->where('estado_cuota', '!=', 'COBRADA')
            ->whereHas('contrato', fn ($q) => $q->where('estado_registro', '!=', 'ANL'))->orderBy('fecha_cuota')->get()->map(fn ($q) => [
                'cliente' => $q->contrato->empresa?->denominacion, 'edificio' => $q->contrato->edificio?->denominacion,
            ] + $this->ubic($q->contrato->edificio) + [
                'contrato' => $q->contrato->codigo_contrato ?? "#{$q->contrato_id}", 'nro_cuota' => $q->nro_cuota, 'fecha_cuota' => $this->fecha($q->fecha_cuota),
                'situacion' => $q->estado_cuota, 'dias_mora' => $q->fecha_cuota->isPast() ? (int) $q->fecha_cuota->diffInDays(today()) : 0, 'monto_bs' => (float) $q->monto_bs,
                '_fecha' => $this->fecha($q->fecha_cuota),
            ])->all();

        return ['columnas' => ['cliente' => 'Cliente', 'edificio' => 'Edificio'] + UbicacionResolver::COLUMNAS + ['contrato' => 'Contrato', 'nro_cuota' => 'Cuota', 'fecha_cuota' => 'Vence', 'situacion' => 'Situación', 'dias_mora' => 'Días de mora', 'monto_bs' => 'Monto (Bs)'], 'filas' => $filas];
    }

    private function reporteCuentasBancarias(): array
    {
        $cobrado = Cobranza::where('estado_registro', '!=', 'ANL')->groupBy('cuenta_bancaria_id')->selectRaw('cuenta_bancaria_id, sum(cobrado_bs) as bs, sum(cobrado_usd) as usd, count(*) as n')->get()->keyBy('cuenta_bancaria_id');

        $filas = CuentaBancaria::with('banco')->get()->map(fn ($c) => [
            'banco' => $c->banco?->nombre, 'cuenta' => $c->numero_cuenta, 'moneda' => $c->moneda, 'cobros' => (int) ($cobrado[$c->id]->n ?? 0),
            'total_cobrado_bs' => round((float) ($cobrado[$c->id]->bs ?? 0), 2), 'cobrado_usd' => round((float) ($cobrado[$c->id]->usd ?? 0), 2),
        ])->all();

        return ['columnas' => ['banco' => 'Banco', 'cuenta' => 'Cuenta', 'moneda' => 'Moneda', 'cobros' => 'Cobros', 'total_cobrado_bs' => 'Cobrado (Bs)', 'cobrado_usd' => 'Cobrado (USD)'], 'filas' => $filas];
    }

    // ------------------------------------------------------------------ RRHH y Secretaría

    private function reportePersonal(): array
    {
        $filas = Personal::with(['cargo', 'profesion'])->orderBy('apellidos')->get()->map(fn ($p) => [
            'ci' => $p->ci, 'nombre' => trim("{$p->apellidos} {$p->nombres}"), 'cargo' => $p->cargo?->nombre, 'profesion' => $p->profesion?->nombre,
            'situacion' => $p->estado ? 'Activo' : 'Inactivo',
        ])->all();

        return ['columnas' => ['ci' => 'CI', 'nombre' => 'Nombre', 'cargo' => 'Cargo', 'profesion' => 'Profesión', 'situacion' => 'Situación'], 'filas' => $filas];
    }

    private function reporteAgenda(): array
    {
        $filas = Cita::with(['empresa', 'edificio'])->orderBy('fecha_inicio')->get()->map(fn ($c) => [
            'inicio' => $c->fecha_inicio->format('Y-m-d H:i'), 'fin' => $c->fecha_fin->format('H:i'), 'tipo' => config('sofia.tipos_cita')[$c->tipo] ?? $c->tipo,
            'asunto' => $c->titulo, 'con_quien' => $c->con_quien, 'telefono' => $c->telefono, 'empresa' => $c->empresa?->denominacion, 'edificio' => $c->edificio?->denominacion,
        ] + $this->ubic($c->edificio ?? $c->empresa) + [
            'lugar' => $c->lugar, 'situacion' => config('sofia.situaciones_cita')[$c->situacion] ?? $c->situacion, 'responsable' => $c->responsable,
            '_fecha' => $this->fecha($c->fecha_inicio),
        ])->all();

        return ['columnas' => ['inicio' => 'Inicio', 'fin' => 'Fin', 'tipo' => 'Tipo', 'asunto' => 'Asunto', 'con_quien' => 'Con quién', 'telefono' => 'Teléfono', 'empresa' => 'Empresa', 'edificio' => 'Edificio'] + UbicacionResolver::COLUMNAS + ['lugar' => 'Lugar', 'situacion' => 'Situación', 'responsable' => 'Agenda de'], 'filas' => $filas];
    }
}
