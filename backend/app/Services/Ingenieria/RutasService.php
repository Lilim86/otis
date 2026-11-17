<?php

namespace App\Services\Ingenieria;

use App\Models\Personal;
use App\Models\VisitaMantenimiento;
use App\Services\TableroTecnicosService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Ruteo de las visitas del día de cada técnico (problema del viajante, TSP).
 *
 * Distancia: Haversine entre edificios × factor de tortuosidad de las calles. Recorrido: sale de la
 * oficina central, pasa por todas las visitas y vuelve. Solución: heurística del vecino más cercano
 * y luego mejora 2-opt (invierte tramos mientras acorte la ruta). Para 4-8 visitas por día llega al
 * óptimo o muy cerca, en milisegundos.
 *
 * "Antes" es el orden en que se cargaron las visitas (o el orden de ruta ya guardado), que es como
 * se trabajaba sin el optimizador.
 */
class RutasService
{
    private const ABIERTAS = ['PROGRAMADA', 'REPROGRAMADA', 'SOLICITADA', 'EN_CURSO'];

    public function __construct(private TableroTecnicosService $tablero) {}

    // ------------------------------------------------------------------ geometría

    /** Distancia por calle estimada (km) entre dos puntos [lat, lon]. */
    public static function km(array $a, array $b): float
    {
        [$lat1, $lon1] = array_map('deg2rad', $a);
        [$lat2, $lon2] = array_map('deg2rad', $b);
        $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lon2 - $lon1) / 2) ** 2;

        return 2 * 6371 * asin(min(1, sqrt($h))) * (float) config('sofia.ingenieria.factor_ruta');
    }

    public static function base(): array
    {
        $b = config('sofia.ingenieria.base');

        return [(float) $b['latitud'], (float) $b['longitud']];
    }

    /** Largo (km) del recorrido base → puntos en ese orden → base. */
    public static function largo(array $puntos): float
    {
        $total = 0.0;
        $anterior = self::base();
        foreach ($puntos as $p) {
            $total += self::km($anterior, $p);
            $anterior = $p;
        }

        return $puntos ? $total + self::km($anterior, self::base()) : 0.0;
    }

    /** Minutos de la jornada: manejo a velocidad promedio + tiempo de servicio en cada edificio. */
    public static function minutos(float $km, int $visitas): float
    {
        return round($km / (float) config('sofia.ingenieria.velocidad_kmh') * 60 + $visitas * (int) config('sofia.ingenieria.minutos_por_visita'), 1);
    }

    /**
     * Orden óptimo (índices) de los puntos: vecino más cercano + 2-opt.
     *
     * @param  array<int, array{0: float, 1: float}>  $puntos
     * @return int[]
     */
    public static function optimizar(array $puntos): array
    {
        $n = count($puntos);
        if ($n < 2) {
            return array_keys($puntos);
        }

        // 1) Vecino más cercano desde la oficina.
        $pendientes = array_keys($puntos);
        $orden = [];
        $actual = self::base();
        while ($pendientes) {
            usort($pendientes, fn ($i, $j) => self::km($actual, $puntos[$i]) <=> self::km($actual, $puntos[$j]));
            $siguiente = array_shift($pendientes);
            $orden[] = $siguiente;
            $actual = $puntos[$siguiente];
        }

        // 2) 2-opt: invierte el tramo i..j si así la ruta se acorta; repite hasta que no mejore.
        $largo = fn (array $o) => self::largo(array_map(fn ($i) => $puntos[$i], $o));
        $mejor = $largo($orden);
        do {
            $mejoro = false;
            for ($i = 0; $i < $n - 1; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $candidato = array_merge(array_slice($orden, 0, $i), array_reverse(array_slice($orden, $i, $j - $i + 1)), array_slice($orden, $j + 1));
                    $l = $largo($candidato);
                    if ($l < $mejor - 1e-9) {
                        [$orden, $mejor, $mejoro] = [$candidato, $l, true];
                    }
                }
            }
        } while ($mejoro);

        return $orden;
    }

    // ------------------------------------------------------------------ rutas del día

    private function visitasDelDia(string $fecha, ?int $tecnicoId = null): Collection
    {
        return VisitaMantenimiento::with(['contrato.edificio:id,denominacion,latitud,longitud', 'contrato.empresa:id,denominacion', 'tecnico:id,nombres,apellidos'])
            ->whereDate('fecha_programada', $fecha)->whereNotNull('tecnico_id')->whereIn('situacion', [...self::ABIERTAS, 'REALIZADA'])
            ->when($tecnicoId, fn ($q) => $q->where('tecnico_id', $tecnicoId))
            ->orderByRaw('orden_ruta IS NULL, orden_ruta')->orderBy('id')->get();
    }

    private static function punto(VisitaMantenimiento $v): ?array
    {
        $e = $v->contrato?->edificio;

        return $e && $e->latitud !== null && $e->longitud !== null ? [(float) $e->latitud, (float) $e->longitud] : null;
    }

    /** Ruta actual y ruta óptima de cada técnico en la fecha, con km, minutos y ahorro. */
    public function planificar(string $fecha, ?int $tecnicoId = null): array
    {
        $rutas = $this->visitasDelDia($fecha, $tecnicoId)->groupBy('tecnico_id')->map(function (Collection $visitas) {
            $conPunto = $visitas->filter(fn ($v) => self::punto($v) !== null)->values();
            $puntos = $conPunto->map(fn ($v) => self::punto($v))->all();
            $orden = self::optimizar($puntos);
            $optimas = collect($orden)->map(fn ($i) => $conPunto[$i]);

            $kmAntes = self::largo($puntos);
            $kmDespues = self::largo(array_map(fn ($i) => $puntos[$i], $orden));
            $t = $visitas->first()->tecnico;
            $parada = fn (VisitaMantenimiento $v) => [
                'id' => $v->id, 'edificio' => $v->contrato?->edificio?->denominacion, 'cliente' => $v->contrato?->empresa?->denominacion,
                'tipo' => $v->tipo, 'situacion' => $v->situacion, 'prioridad' => $v->prioridad, 'punto' => self::punto($v),
            ];

            return [
                'tecnico_id' => $t->id, 'tecnico' => trim("{$t->nombres} {$t->apellidos}"), 'visitas' => $visitas->count(),
                'sin_ubicacion' => $visitas->count() - $conPunto->count(),
                'actual' => $conPunto->map($parada)->values(), 'optima' => $optimas->map($parada)->values(),
                'km_antes' => round($kmAntes, 2), 'km_despues' => round($kmDespues, 2),
                'minutos_antes' => self::minutos($kmAntes, $conPunto->count()), 'minutos_despues' => self::minutos($kmDespues, $conPunto->count()),
                'ahorro_km' => round($kmAntes - $kmDespues, 2), 'ahorro_pct' => $kmAntes > 0 ? round(($kmAntes - $kmDespues) / $kmAntes * 100, 1) : 0.0,
            ];
        })->values();

        return [
            'fecha' => $fecha, 'base' => self::base(), 'rutas' => $rutas,
            'total' => [
                'km_antes' => round($rutas->sum('km_antes'), 2), 'km_despues' => round($rutas->sum('km_despues'), 2),
                'ahorro_km' => round($rutas->sum('ahorro_km'), 2),
                'ahorro_bs' => round($rutas->sum('ahorro_km') * (float) config('sofia.ingenieria.costo_bs_km'), 2),
                'ahorro_minutos' => round($rutas->sum('minutos_antes') - $rutas->sum('minutos_despues'), 1),
            ],
        ];
    }

    /** Guarda el orden óptimo en las visitas (lo ve el técnico en "Mi ruta") y registra el antes y el después. */
    public function aplicar(string $fecha, ?int $usuarioId): array
    {
        $plan = $this->planificar($fecha);

        DB::transaction(function () use ($plan, $fecha, $usuarioId) {
            foreach ($plan['rutas'] as $r) {
                foreach ($r['optima'] as $i => $parada) {
                    VisitaMantenimiento::whereKey($parada['id'])->update(['orden_ruta' => $i + 1]);
                }
                if ($r['optima']->count() > 1) {
                    DB::table('optimizaciones_ruta')->insert([
                        'fecha_ruta' => $fecha, 'tecnico_id' => $r['tecnico_id'], 'visitas' => $r['optima']->count(),
                        'km_antes' => $r['km_antes'], 'km_despues' => $r['km_despues'], 'minutos_antes' => $r['minutos_antes'], 'minutos_despues' => $r['minutos_despues'],
                        'metodo' => 'Vecino más cercano + 2-opt', 'aplicado_por' => $usuarioId, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });

        return $plan;
    }

    /** Ahorro acumulado de todas las rutas aplicadas. */
    public function historial(): array
    {
        $filas = DB::table('optimizaciones_ruta')->get();
        $ahorro = (float) $filas->sum(fn ($f) => $f->km_antes - $f->km_despues);
        $antes = (float) $filas->sum('km_antes');

        return [
            'rutas_aplicadas' => $filas->count(),
            'km_antes' => round($antes, 2), 'km_despues' => round((float) $filas->sum('km_despues'), 2),
            'ahorro_km' => round($ahorro, 2), 'ahorro_pct' => $antes > 0 ? round($ahorro / $antes * 100, 1) : 0.0,
            'ahorro_bs' => round($ahorro * (float) config('sofia.ingenieria.costo_bs_km'), 2),
            'ahorro_horas' => round((float) $filas->sum(fn ($f) => $f->minutos_antes - $f->minutos_despues) / 60, 1),
        ];
    }

    // ------------------------------------------------------------------ asignación por cercanía

    /**
     * Compara, sin guardar nada, dos formas de repartir las visitas sin técnico:
     *  - "por carga": la de siempre, al técnico con menos visitas (TableroTecnicosService::autoasignar).
     *  - "por cercanía": al técnico al que la visita le alarga menos su ruta del día (costo de inserción),
     *    respetando el máximo de visitas por día y equilibrando la carga.
     * Los km de cada día se miden ya con la ruta optimizada, para comparar solo el efecto de la asignación.
     */
    public function compararAsignacion(string $desde, string $hasta): array
    {
        $tecnicos = $this->tablero->tecnicos()->pluck('id')->all();
        $pendientes = $this->pendientes($desde, $hasta);
        $asignadas = VisitaMantenimiento::with('contrato.edificio:id,latitud,longitud')->whereNotNull('tecnico_id')->whereIn('tecnico_id', $tecnicos)
            ->whereIn('situacion', self::ABIERTAS)->whereDate('fecha_programada', '>=', $desde)->whereDate('fecha_programada', '<=', $hasta)->get();

        $porCarga = $this->simular($pendientes, $asignadas, $tecnicos, 'carga');
        $porCercania = $this->simular($pendientes, $asignadas, $tecnicos, 'cercania');
        $km = fn ($r) => round(collect($r['dias'])->sum(fn ($dia) => collect($dia)->sum(fn ($puntos) => self::largo(array_map(fn ($i) => $puntos[$i], self::optimizar($puntos))))), 2);
        [$kmCarga, $kmCercania] = [$km($porCarga), $km($porCercania)];

        return [
            'desde' => $desde, 'hasta' => $hasta, 'visitas_sin_tecnico' => $pendientes->count(), 'tecnicos' => count($tecnicos),
            'por_carga' => ['km' => $kmCarga, 'asignadas' => $porCarga['asignadas'], 'sin_cupo' => $porCarga['sin_cupo']],
            'por_cercania' => ['km' => $kmCercania, 'asignadas' => $porCercania['asignadas'], 'sin_cupo' => $porCercania['sin_cupo']],
            'ahorro_km' => round($kmCarga - $kmCercania, 2), 'ahorro_pct' => $kmCarga > 0 ? round(($kmCarga - $kmCercania) / $kmCarga * 100, 1) : 0.0,
            'ahorro_bs' => round(($kmCarga - $kmCercania) * (float) config('sofia.ingenieria.costo_bs_km'), 2),
        ];
    }

    /** Asigna de verdad con el criterio de cercanía. */
    public function asignarPorCercania(string $desde, string $hasta): array
    {
        $tecnicos = $this->tablero->tecnicos()->pluck('id')->all();
        $pendientes = $this->pendientes($desde, $hasta);
        $asignadas = VisitaMantenimiento::with('contrato.edificio:id,latitud,longitud')->whereIn('tecnico_id', $tecnicos)
            ->whereIn('situacion', self::ABIERTAS)->whereDate('fecha_programada', '>=', $desde)->whereDate('fecha_programada', '<=', $hasta)->get();
        $r = $this->simular($pendientes, $asignadas, $tecnicos, 'cercania');

        DB::transaction(function () use ($r) {
            foreach ($r['eleccion'] as $visitaId => $tecnicoId) {
                VisitaMantenimiento::whereKey($visitaId)->update(['tecnico_id' => $tecnicoId, 'asignada_automaticamente' => true]);
            }
        });

        return ['asignadas' => $r['asignadas'], 'sin_cupo' => $r['sin_cupo']];
    }

    private function pendientes(string $desde, string $hasta): Collection
    {
        return VisitaMantenimiento::with('contrato.edificio:id,latitud,longitud')->whereNull('tecnico_id')
            ->whereIn('situacion', ['PROGRAMADA', 'REPROGRAMADA', 'SOLICITADA'])
            ->whereDate('fecha_programada', '>=', $desde)->whereDate('fecha_programada', '<=', $hasta)
            // Las fallas más graves eligen técnico primero.
            ->orderBy('fecha_programada')->orderByRaw("COALESCE(prioridad, 'P9')")->get();
    }

    /** @return array{dias: array, asignadas: int, sin_cupo: int, eleccion: array<int,int>} */
    private function simular(Collection $pendientes, Collection $asignadas, array $tecnicos, string $criterio): array
    {
        $maximo = (int) config('sofia.mantenimiento.max_visitas_por_dia');
        // dias[fecha][tecnico] = lista de puntos; carga[tecnico] = visitas abiertas en el periodo.
        $dias = [];
        $carga = array_fill_keys($tecnicos, 0);
        foreach ($asignadas as $v) {
            $dias[$v->fecha_programada->toDateString()][$v->tecnico_id][] = self::punto($v) ?? self::base();
            $carga[$v->tecnico_id] = ($carga[$v->tecnico_id] ?? 0) + 1;
        }

        $eleccion = [];
        $sinCupo = 0;
        foreach ($pendientes as $v) {
            $fecha = $v->fecha_programada->toDateString();
            $punto = self::punto($v) ?? self::base();
            $libres = array_values(array_filter($tecnicos, fn ($t) => count($dias[$fecha][$t] ?? []) < $maximo));
            if (! $libres) {
                $sinCupo++;

                continue;
            }

            usort($libres, function ($a, $b) use ($criterio, $dias, $fecha, $punto, $carga) {
                if ($criterio === 'carga') {
                    return [$carga[$a], count($dias[$fecha][$a] ?? [])] <=> [$carga[$b], count($dias[$fecha][$b] ?? [])];
                }

                // Costo de inserción en km + 1 km de penalización por visita que ya tiene (equilibra la carga).
                return $this->costoInsercion($dias[$fecha][$a] ?? [], $punto) + 1.0 * $carga[$a]
                    <=> $this->costoInsercion($dias[$fecha][$b] ?? [], $punto) + 1.0 * $carga[$b];
            });

            $elegido = $libres[0];
            $dias[$fecha][$elegido][] = $punto;
            $carga[$elegido]++;
            $eleccion[$v->id] = $elegido;
        }

        return ['dias' => $dias, 'asignadas' => count($eleccion), 'sin_cupo' => $sinCupo, 'eleccion' => $eleccion];
    }

    /** Cuántos km agrega el punto a la ruta (mejor posición de inserción). */
    private function costoInsercion(array $ruta, array $punto): float
    {
        $nodos = [self::base(), ...$ruta, self::base()];
        $mejor = INF;
        for ($i = 0; $i < count($nodos) - 1; $i++) {
            $mejor = min($mejor, self::km($nodos[$i], $punto) + self::km($punto, $nodos[$i + 1]) - self::km($nodos[$i], $nodos[$i + 1]));
        }

        return $mejor;
    }

    public function tecnicoNombre(int $id): ?string
    {
        $t = Personal::find($id);

        return $t ? trim("{$t->nombres} {$t->apellidos}") : null;
    }
}
