<?php

namespace App\Services\Ingenieria;

use App\Models\VisitaMantenimiento;
use App\Services\CronogramaService;
use App\Services\TableroTecnicosService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Triage de las fallas que reporta el cliente: lee la descripción, la clasifica en P1..P4 con un
 * puntaje por palabras clave (con su peso) y le fija un tiempo objetivo de respuesta.
 * Las P1 y P2 se asignan solas al técnico que puede llegar antes (el más cercano con su ruta del día).
 *
 *  P1 Crítica  personas atrapadas, riesgo físico                → 2 h
 *  P2 Alta     equipo detenido o fuera de servicio               → 8 h (mismo día)
 *  P3 Media    funciona con fallas (ruidos, desnivel, puertas)   → 48 h
 *  P4 Baja     consultas, detalles estéticos                     → 5 días
 */
class PrioridadFallasService
{
    /** Palabras (sin tildes, en minúscula) y su peso. Un puntaje ≥ 10 es P1, ≥ 6 P2, ≥ 3 P3. */
    private const PALABRAS = [
        'atrapad' => 10, 'encerrad' => 10, 'persona dentro' => 10, 'personas dentro' => 10, 'rescate' => 10, 'nino dentro' => 10,
        'humo' => 10, 'fuego' => 10, 'incendio' => 10, 'chispa' => 9, 'electrocu' => 10, 'cayo' => 8, 'caida' => 8, 'desplom' => 10, 'cable roto' => 9, 'freno' => 7,
        'no funciona' => 6, 'detenid' => 6, 'parado' => 6, 'paralizad' => 6, 'no arranca' => 6, 'no responde' => 6, 'entre pisos' => 8, 'fuera de servicio' => 6,
        'no abre' => 6, 'no cierra' => 5, 'sin energia' => 6, 'inund' => 7, 'agua en' => 6, 'se detiene' => 6, 'se para' => 6,
        'ruido' => 3, 'vibra' => 3, 'desnivel' => 4, 'no nivela' => 4, 'golpe' => 3, 'brusco' => 3, 'lento' => 3, 'tarda' => 3, 'atasca' => 4,
        'boton' => 3, 'pulsador' => 3, 'display' => 2, 'olor' => 4, 'luz' => 2, 'alarma' => 3, 'intercomunicador' => 3, 'citofono' => 2,
        'puerta' => 2, 'rayado' => 1, 'limpieza' => 1, 'consulta' => 0,
    ];

    private const NOMBRES = ['P1' => 'Crítica', 'P2' => 'Alta', 'P3' => 'Media', 'P4' => 'Baja'];

    public function __construct(private CronogramaService $cronograma, private TableroTecnicosService $tablero) {}

    /** @return array{prioridad: string, nombre: string, puntaje: int, palabras: string[], objetivo_horas: int} */
    public function clasificar(string $descripcion, bool $urgente = false): array
    {
        $texto = Str::of($descripcion)->lower()->ascii()->squish()->toString();
        $encontradas = collect(self::PALABRAS)->filter(fn ($peso, $palabra) => str_contains($texto, $palabra));
        // Se suma la palabra más grave y la mitad del resto (varias señales leves no hacen una crítica).
        $pesos = $encontradas->sortDesc()->values();
        $puntaje = (int) round(($pesos->first() ?? 0) + $pesos->slice(1)->sum() / 2);

        $prioridad = match (true) {
            $puntaje >= 10 => 'P1',
            $puntaje >= 6 => 'P2',
            $puntaje >= 3 => 'P3',
            default => 'P4',
        };
        // Si el cliente marcó "urgente" se sube al menos a P2.
        if ($urgente && in_array($prioridad, ['P3', 'P4'], true)) {
            $prioridad = 'P2';
        }

        return [
            'prioridad' => $prioridad, 'nombre' => self::NOMBRES[$prioridad], 'puntaje' => $puntaje,
            'palabras' => $encontradas->keys()->values()->all(), 'objetivo_horas' => (int) config("sofia.ingenieria.objetivo_horas.{$prioridad}"),
        ];
    }

    /** Fecha de atención según la prioridad (la del cliente solo se respeta si no es una urgencia). */
    public function fechaAtencion(string $prioridad, ?string $fechaDeseada): Carbon
    {
        return match ($prioridad) {
            'P1', 'P2' => today(),
            'P3' => $fechaDeseada ? Carbon::parse($fechaDeseada) : $this->cronograma->sumarDiasHabiles(today(), 1),
            default => $fechaDeseada ? Carbon::parse($fechaDeseada) : $this->cronograma->sumarDiasHabiles(today(), 3),
        };
    }

    /**
     * Técnico para una urgencia: el que llega antes al edificio. Se toma su posición actual
     * (la visita en curso, o la última del día, o la oficina) y se suma una penalización por su carga del día.
     * Las urgencias no respetan el máximo de visitas por día.
     */
    public function tecnicoMasCercano(VisitaMantenimiento $visita): ?int
    {
        $destino = $visita->contrato?->edificio;
        if (! $destino || $destino->latitud === null) {
            return null;
        }
        $punto = [(float) $destino->latitud, (float) $destino->longitud];

        $mejor = null;
        foreach ($this->tablero->tecnicos() as $t) {
            $hoy = VisitaMantenimiento::with('contrato.edificio:id,latitud,longitud')->where('tecnico_id', $t->id)->whereDate('fecha_programada', today())
                ->whereIn('situacion', ['PROGRAMADA', 'REPROGRAMADA', 'SOLICITADA', 'EN_CURSO'])->orderByRaw('orden_ruta IS NULL, orden_ruta')->get();
            $ancla = $hoy->firstWhere('situacion', 'EN_CURSO') ?? $hoy->first();
            $desde = $ancla?->contrato?->edificio?->latitud !== null && $ancla ? [(float) $ancla->contrato->edificio->latitud, (float) $ancla->contrato->edificio->longitud] : RutasService::base();
            $costo = RutasService::km($desde, $punto) + 2.0 * $hoy->count();
            if ($mejor === null || $costo < $mejor[1]) {
                $mejor = [$t->id, $costo];
            }
        }

        return $mejor[0] ?? null;
    }
}
