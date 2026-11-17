<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Cobranza;
use App\Models\Contrato;
use App\Models\CuotaContrato;
use App\Models\Departamento;
use App\Models\Edificio;
use App\Models\Empresa;
use App\Models\Equipo;
use App\Models\Pais;
use App\Models\Personal;
use App\Models\Profesion;
use App\Models\VisitaMantenimiento;
use App\Services\CronogramaService;
use App\Services\Ingenieria\PrioridadFallasService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Datos INVENTADOS para demostrar los aportes de ingeniería (rutas, confiabilidad, prioridad de fallas
 * y analítica de cobranza). Las coordenadas son de zonas reales de La Paz; empresas y edificios no existen.
 * Ejecutar: php artisan db:seed --class=IngenieriaDemoSeeder   (si ya se cargó, no hace nada)
 */
class IngenieriaDemoSeeder extends Seeder
{
    /** Fecha desde la que las fallas se clasifican solas (antes se atendían por orden de llegada). */
    private const INICIO_TRIAGE = '2026-09-01';

    private const EDIFICIOS = [
        ['ING-01', 'EDIFICIO MIRADOR CENTRAL', 'Centro', -16.4958, -68.1336],
        ['ING-02', 'TORRE SOPOCACHI', 'Sopocachi', -16.5090, -68.1270],
        ['ING-03', 'EDIFICIO SAN JORGE PLAZA', 'San Jorge', -16.5140, -68.1225],
        ['ING-04', 'CONDOMINIO MIRAFLORES', 'Miraflores', -16.5030, -68.1180],
        ['ING-05', 'TORRE OBRAJES', 'Obrajes', -16.5280, -68.1080],
        ['ING-06', 'EDIFICIO CALACOTO 21', 'Calacoto', -16.5410, -68.0790],
        ['ING-07', 'CENTRO EMPRESARIAL SAN MIGUEL', 'San Miguel', -16.5390, -68.0760],
        ['ING-08', 'RESIDENCIAL ACHUMANI', 'Achumani', -16.5300, -68.0650],
        ['ING-09', 'EDIFICIO IRPAVI VERDE', 'Irpavi', -16.5230, -68.0830],
        ['ING-10', 'TORRE SEGUENCOMA', 'Següencoma', -16.5420, -68.0950],
        ['ING-11', 'EDIFICIO VILLA FATIMA', 'Villa Fátima', -16.4840, -68.1150],
        ['ING-12', 'EDIFICIO SAN PEDRO', 'San Pedro', -16.5010, -68.1390],
        ['ING-13', 'TORRE LOS PINOS', 'Los Pinos', -16.5480, -68.0690],
        ['ING-14', 'CONDOMINIO COTA COTA', 'Cota Cota', -16.5390, -68.0650],
    ];

    /** Clientes y su forma de pagar: [razón social, nit, edificios, días de atraso mín-máx, cuotas recientes impagas]. */
    private const CLIENTES = [
        ['INMOBILIARIA ANDINA DEMO S.A.', 'DEMO-ING-1', ['ING-01', 'ING-12'], [0, 3], 0],
        ['ADMINISTRADORA SUR DEMO S.R.L.', 'DEMO-ING-2', ['ING-06', 'ING-07', 'ING-13'], [0, 8], 0],
        ['COPROPIETARIOS TORRE SOPOCACHI DEMO', 'DEMO-ING-3', ['ING-02', 'ING-03'], [8, 25], 0],
        ['CONSTRUCTORA ILLIMANI DEMO LTDA.', 'DEMO-ING-4', ['ING-05', 'ING-10'], [30, 70], 2],
        ['GRUPO MIRAFLORES DEMO S.A.', 'DEMO-ING-5', ['ING-04', 'ING-11'], [5, 40], 1],
        ['RESIDENCIAS DEL SUR DEMO', 'DEMO-ING-6', ['ING-08', 'ING-09', 'ING-14'], [0, 12], 0],
    ];

    /** Fallas por edificio en el último año (unos fallan mucho, otros casi nada). */
    private const FALLAS = ['ING-01' => 1, 'ING-02' => 2, 'ING-03' => 0, 'ING-04' => 6, 'ING-05' => 4, 'ING-06' => 1, 'ING-07' => 2,
        'ING-08' => 0, 'ING-09' => 3, 'ING-10' => 7, 'ING-11' => 1, 'ING-12' => 0, 'ING-13' => 2, 'ING-14' => 1];

    private const DESCRIPCIONES = [
        'Hay personas atrapadas en la cabina, el ascensor se detuvo entre pisos',
        'El ascensor está detenido en el piso 3 y no funciona',
        'La puerta no abre en planta baja',
        'Ruido fuerte al subir y vibra en los últimos pisos',
        'La cabina no nivela bien con el piso, queda un desnivel',
        'El botón del piso 5 no responde',
        'El ascensor se detiene solo y vuelve a arrancar',
        'La luz de la cabina parpadea',
    ];

    public function run(): void
    {
        if (Empresa::where('nit', 'DEMO-ING-1')->exists()) {
            $this->command?->info('Los datos de ingeniería ya estaban cargados.');

            return;
        }
        mt_srand(2026);
        $cronograma = app(CronogramaService::class);
        $triage = app(PrioridadFallasService::class);

        $pais = Pais::where('codigo_iso', 'BO')->first();
        $departamento = $pais ? Departamento::where('pais_id', $pais->id)->where('nombre', 'LA PAZ')->value('id') : null;

        $cargo = Cargo::firstOrCreate(['nombre' => 'TECNICO'])->id;
        $profesion = Profesion::firstOrCreate(['nombre' => 'TECNICO ELECTROMECANICO'])->id;
        $tecnicos = collect([['DEMO-201', 'Carlos', 'Mamani'], ['DEMO-202', 'Lucía', 'Quispe'], ['DEMO-203', 'Jorge', 'Choque']])
            ->map(fn ($t) => Personal::firstOrCreate(['ci' => $t[0]], ['cargo_id' => $cargo, 'profesion_id' => $profesion, 'nombres' => $t[1], 'apellidos' => $t[2]]));
        $tecnico = fn () => $tecnicos[mt_rand(0, $tecnicos->count() - 1)]->id;

        $edificios = collect(self::EDIFICIOS)->mapWithKeys(fn ($e) => [$e[0] => Edificio::firstOrCreate(['codigo_edificio' => $e[0]], [
            'denominacion' => $e[1], 'tipo_edificacion' => 'EDIFICIO MULTIFAMILIAR', 'referencia_cercana' => "Zona {$e[2]}",
            'latitud' => $e[3], 'longitud' => $e[4], 'pais_id' => $pais?->id, 'departamento_id' => $departamento,
        ])]);

        $contratos = collect();
        foreach (self::CLIENTES as $i => [$nombre, $nit, $codigos, $atraso, $impagas]) {
            $empresa = Empresa::create(['denominacion' => $nombre, 'nit' => $nit, 'tipo_empresa' => 'EMPRESA - CLIENTE', 'pais_id' => $pais?->id, 'departamento_id' => $departamento]);
            foreach ($codigos as $codigo) {
                $edificio = $edificios[$codigo];
                $edificio->update(['empresa_id' => $empresa->id]);
                foreach (range(1, mt_rand(1, 3)) as $n) {
                    Equipo::firstOrCreate(['codigo' => "{$codigo}-ASC-{$n}"], ['descripcion' => "Ascensor de pasajeros {$n}", 'edificio_id' => $edificio->id, 'marca' => 'OTIS', 'modelo' => 'Gen2', 'paradas' => mt_rand(6, 16)]);
                }

                // El último cliente es nuevo: contrato de hace dos meses.
                $inicio = today()->subMonths($i === 5 ? 2 : 11)->startOfMonth();
                $mensual = mt_rand(25, 90) * 100;
                $contrato = Contrato::create([
                    'empresa_id' => $empresa->id, 'edificio_id' => $edificio->id, 'codigo_contrato' => "C-{$codigo}",
                    'fecha_inicio' => $inicio->toDateString(), 'fecha_fin' => $inicio->copy()->addYear()->subDay()->toDateString(), 'monto_total' => $mensual * 12,
                ]);
                $contratos->push($contrato);
                $this->cuotasYPagos($contrato, $mensual, $atraso, $impagas);
            }
        }

        // Cronograma preventivo: lo pasado se dio por hecho (casi siempre a tiempo); lo próximo queda sin técnico.
        foreach ($contratos as $contrato) {
            foreach ($cronograma->generar($contrato) as $visita) {
                if ($visita->fecha_programada->lt(today())) {
                    $demora = mt_rand(1, 100) <= 80 ? 0 : mt_rand(1, 6);
                    $realizada = $visita->fecha_programada->copy()->addDays($demora);
                    $visita->update([
                        'situacion' => 'REALIZADA', 'fecha_realizada' => $realizada->toDateString(), 'tecnico_id' => $tecnico(),
                        'iniciada_en' => $realizada->copy()->setTime(mt_rand(8, 15), mt_rand(0, 59)), 'calificacion' => mt_rand(1, 100) <= 60 ? mt_rand(4, 5) : null,
                        'informe' => 'Revisión de cabina, puertas, cables, frenos y tablero. Lubricación general.',
                    ]);
                }
            }
        }

        // Fallas del último año. Antes del triage se atendían por orden de llegada (más lento para las graves).
        foreach (self::FALLAS as $codigo => $cantidad) {
            $contrato = $contratos->firstWhere('edificio_id', $edificios[$codigo]->id);
            for ($k = 0; $k < $cantidad; $k++) {
                $aviso = Carbon::parse($contrato->fecha_inicio)->addDays(mt_rand(5, max(6, (int) Carbon::parse($contrato->fecha_inicio)->diffInDays(today()) - 2)))->setTime(mt_rand(7, 19), mt_rand(0, 59));
                $descripcion = self::DESCRIPCIONES[mt_rand(0, count(self::DESCRIPCIONES) - 1)];
                $clase = $triage->clasificar($descripcion);
                $conTriage = $aviso->gte(self::INICIO_TRIAGE);
                $horas = $conTriage
                    ? $clase['objetivo_horas'] * mt_rand(35, 110) / 100
                    : mt_rand(10, 52) + ($clase['prioridad'] === 'P1' ? 0 : mt_rand(0, 24));
                $llegada = $aviso->copy()->addMinutes((int) ($horas * 60));

                $visita = VisitaMantenimiento::create([
                    'contrato_id' => $contrato->id, 'tipo' => 'CORRECTIVO', 'situacion' => 'REALIZADA', 'descripcion' => $descripcion,
                    'fecha_programada' => $aviso->toDateString(), 'fecha_realizada' => $llegada->toDateString(), 'iniciada_en' => $llegada, 'tecnico_id' => $tecnico(),
                    'informe' => 'Falla atendida y equipo en funcionamiento.', 'calificacion' => mt_rand(3, 5),
                    'prioridad' => $conTriage ? $clase['prioridad'] : null,
                    'objetivo_respuesta_en' => $conTriage ? $aviso->copy()->addHours($clase['objetivo_horas']) : null,
                ]);
                $visita->forceFill(['created_at' => $aviso, 'updated_at' => $llegada])->saveQuietly();
            }
        }

        // Ruta de hoy: 5 visitas por técnico, cargadas en el orden en que llamaron los clientes (zigzag por la ciudad).
        $hoy = collect(['ING-12', 'ING-14', 'ING-02', 'ING-07', 'ING-11', 'ING-05', 'ING-01', 'ING-13', 'ING-04', 'ING-08', 'ING-03', 'ING-10', 'ING-09', 'ING-06', 'ING-12']);
        foreach ($hoy as $n => $codigo) {
            $contrato = $contratos->firstWhere('edificio_id', $edificios[$codigo]->id);
            VisitaMantenimiento::create([
                'contrato_id' => $contrato->id, 'tipo' => 'PREVENTIVO', 'situacion' => 'PROGRAMADA', 'fecha_programada' => today()->toDateString(),
                'nro_visita' => (int) VisitaMantenimiento::where('contrato_id', $contrato->id)->max('nro_visita') + 1,
                'descripcion' => 'Inspección de seguridad programada', 'tecnico_id' => $tecnicos[$n % 3]->id,
            ]);
        }

        $this->command?->info('Datos de ingeniería cargados: 14 edificios, 6 clientes, 3 técnicos.');
    }

    /** 12 cuotas mensuales y sus cobros según el hábito de pago del cliente. */
    private function cuotasYPagos(Contrato $contrato, int $mensual, array $atraso, int $impagas): void
    {
        $inicio = Carbon::parse($contrato->fecha_inicio);
        $vencidas = [];
        for ($n = 1; $n <= 12; $n++) {
            $fecha = $inicio->copy()->addMonths($n - 1)->addDays(9);
            $cuota = CuotaContrato::create(['contrato_id' => $contrato->id, 'nro_cuota' => $n, 'fecha_cuota' => $fecha->toDateString(), 'monto_bs' => $mensual, 'concepto' => 'Servicio de mantenimiento integral']);
            if ($fecha->lt(today())) {
                $vencidas[] = $cuota;
            }
        }

        foreach ($vencidas as $i => $cuota) {
            if ($i >= count($vencidas) - $impagas) {
                continue; // las más recientes quedan impagas
            }
            $pago = $cuota->fecha_cuota->copy()->addDays(mt_rand($atraso[0], $atraso[1]));
            if ($pago->gt(today())) {
                continue;
            }
            Cobranza::create([
                'contrato_id' => $contrato->id, 'cuota_id' => $cuota->id, 'concepto' => 'Servicio de mantenimiento integral',
                'monto_facturado' => $mensual, 'cobrado_bs' => $mensual, 'fecha_cobro' => $pago->toDateString(), 'fecha_facturacion' => $cuota->fecha_cuota->toDateString(),
                'nro_factura' => (string) (200 + $cuota->id), 'comprobante_deposito' => 'TRX-'.mt_rand(100000, 999999), 'estado' => true,
            ]);
            $cuota->actualizarPorCobros();
        }
    }
}
