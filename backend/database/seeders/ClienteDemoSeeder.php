<?php

namespace Database\Seeders;

use App\Models\Banco;
use App\Models\Cargo;
use App\Models\Cita;
use App\Models\Cobranza;
use App\Models\Contrato;
use App\Models\CuentaBancaria;
use App\Models\CuotaContrato;
use App\Models\Departamento;
use App\Models\Edificio;
use App\Models\Empresa;
use App\Models\Equipo;
use App\Models\Pais;
use App\Models\Personal;
use App\Models\Profesion;
use App\Models\User;
use App\Models\VisitaMantenimiento;
use App\Services\CronogramaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de ejemplo INVENTADOS para probar el portal del cliente. No usa ningún dato real.
 * Ejecutar a mano: php artisan db:seed --class=ClienteDemoSeeder   (es repetible)
 */
class ClienteDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermisosSeeder::class);

        if ($numero = config('sofia.pagos.cuenta_destino')) {
            CuentaBancaria::firstOrCreate(['numero_cuenta' => $numero], ['banco_id' => Banco::firstOrCreate(['nombre' => config('sofia.pagos.banco')])->id, 'moneda' => 'BOB']);
        }

        $pais = Pais::where('codigo_iso', 'BO')->first();
        $empresa = Empresa::firstOrCreate(['nit' => 'DEMO-0001'], [
            'denominacion' => 'CLIENTE DEMO S.R.L.', 'tipo_empresa' => 'EMPRESA - CLIENTE', 'sigla' => 'DEMO',
            'pais_id' => $pais?->id, 'departamento_id' => $pais ? Departamento::where('pais_id', $pais->id)->where('nombre', 'LA PAZ')->value('id') : null,
        ]);
        $edificio = Edificio::firstOrCreate(['codigo_edificio' => 'DEMO-01'], [
            'denominacion' => 'EDIFICIO DEMO CENTRAL', 'tipo_edificacion' => 'EDIFICIO DE OFICINAS', 'empresa_id' => $empresa->id,
            'pais_id' => $empresa->pais_id, 'departamento_id' => $empresa->departamento_id,
        ]);
        foreach ([['DEMO-ASC-01', 'Ascensor de pasajeros torre A'], ['DEMO-ASC-02', 'Ascensor de pasajeros torre B']] as [$codigo, $descripcion]) {
            Equipo::firstOrCreate(['codigo' => $codigo], ['descripcion' => $descripcion, 'edificio_id' => $edificio->id, 'marca' => 'OTIS', 'paradas' => 8]);
        }

        $inicio = today()->subDays(80);
        $contrato = Contrato::firstOrCreate(['codigo_contrato' => 'C-DEMO-001'], [
            'empresa_id' => $empresa->id, 'edificio_id' => $edificio->id, 'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $inicio->copy()->addYear()->toDateString(), 'monto_total' => 12000,
        ]);

        // Cuotas trimestrales: la primera cobrada, la segunda vencida, las demás por venir.
        foreach ([0, 1, 2, 3] as $i) {
            CuotaContrato::firstOrCreate(['contrato_id' => $contrato->id, 'nro_cuota' => $i + 1], [
                'fecha_cuota' => $inicio->copy()->addMonths($i * 3)->addDays(5)->toDateString(), 'monto_bs' => 3000, 'concepto' => 'Mantenimiento trimestral',
            ]);
        }
        $primera = CuotaContrato::where('contrato_id', $contrato->id)->where('nro_cuota', 1)->first();
        if ($primera->estado_cuota !== 'COBRADA') {
            Cobranza::create([
                'contrato_id' => $contrato->id, 'cuota_id' => $primera->id, 'concepto' => 'Cuota 1 (demo)', 'monto_facturado' => 3000,
                'cobrado_bs' => 3000, 'fecha_cobro' => $primera->fecha_cuota, 'estado' => true,
            ]);
            $primera->actualizarPorCobros();
        }

        $tecnico = Personal::firstOrCreate(['ci' => 'DEMO-100'], [
            'cargo_id' => Cargo::firstOrCreate(['nombre' => 'TECNICO'])->id, 'profesion_id' => Profesion::firstOrCreate(['nombre' => 'TECNICO ELECTROMECANICO'])->id,
            'nombres' => 'Técnico', 'apellidos' => 'Demo',
        ]);

        // Cronograma de 30 días hábiles; las visitas ya vencidas se dan por realizadas.
        $visitas = app(CronogramaService::class)->generar($contrato);
        foreach ($visitas->where('fecha_programada', '<', today()) as $visita) {
            $visita->update([
                'situacion' => 'REALIZADA', 'fecha_realizada' => $visita->fecha_programada, 'tecnico_id' => $tecnico->id,
                'informe' => 'Se revisaron cabina, puertas, cables y sistema de frenos. Lubricación realizada. Sin novedades.',
            ]);
        }
        $primeraVisita = $visitas->sortBy('fecha_programada')->first();
        if ($primeraVisita && ! $primeraVisita->calificacion) {
            $primeraVisita->update(['calificacion' => 5, 'comentario_cliente' => 'Puntuales y ordenados.']);
        }

        VisitaMantenimiento::firstOrCreate(['contrato_id' => $contrato->id, 'tipo' => 'CORRECTIVO', 'descripcion' => 'Puerta de cabina se atasca al cerrar (demo)'], [
            'fecha_programada' => today()->subDays(20)->toDateString(), 'fecha_realizada' => today()->subDays(19)->toDateString(), 'situacion' => 'REALIZADA',
            'tecnico_id' => $tecnico->id, 'informe' => 'Se ajustó el patín de la puerta y se cambió el sensor. Funcionamiento normal.',
        ]);

        // Las próximas visitas quedan asignadas al técnico demo, que entra con su propio usuario.
        VisitaMantenimiento::where('contrato_id', $contrato->id)->where('situacion', 'PROGRAMADA')->orderBy('fecha_programada')->limit(3)->update(['tecnico_id' => $tecnico->id]);
        $this->usuario('tecnico.demo', 'Técnico Demo', 'tecnico.demo@example.com', 'Tecnico123!', 'Técnico', ['personal_id' => $tecnico->id]);

        // El cliente demo también aparece del lado de la empresa: avisos de mantenimiento, agenda del gerente y cobros.
        VisitaMantenimiento::firstOrCreate(['contrato_id' => $contrato->id, 'tipo' => 'CORRECTIVO', 'descripcion' => '[URGENTE] El ascensor se detiene entre pisos (demo)'], [
            'fecha_programada' => today()->addDay()->toDateString(), 'situacion' => 'SOLICITADA',
        ]);
        $citas = [
            ['titulo' => 'Renovación del contrato C-DEMO-001', 'tipo' => 'REUNION', 'dias' => 1, 'hora' => 10, 'motivo' => 'Revisar condiciones y precio de la renovación.'],
            ['titulo' => 'Visita técnica con el administrador del edificio', 'tipo' => 'VISITA', 'dias' => 3, 'hora' => 15, 'motivo' => 'Recorrido por los equipos y presentación del informe.'],
        ];
        foreach ($citas as $c) {
            Cita::firstOrCreate(['titulo' => $c['titulo'], 'empresa_id' => $empresa->id], [
                'tipo' => $c['tipo'], 'responsable' => config('sofia.gerente_general'), 'con_quien' => 'Administrador de '.$empresa->denominacion, 'lugar' => 'Oficina central',
                'fecha_inicio' => today()->addDays($c['dias'])->setHour($c['hora'])->toDateTimeString(), 'fecha_fin' => today()->addDays($c['dias'])->setHour($c['hora'] + 1)->toDateTimeString(),
                'edificio_id' => $edificio->id, 'motivo' => $c['motivo'], 'situacion' => 'PROGRAMADA',
            ]);
        }
        $this->usuario('secretaria.demo', 'Secretaría Demo', 'secretaria.demo@example.com', 'Secretaria123!', 'Secretaría', []);
        $this->usuario('cobrador.demo', 'Cobrador Demo', 'cobrador.demo@example.com', 'Cobrador123!', 'Cobrador', []);

        $usuario = User::firstOrNew(['username' => 'cliente.demo']);
        if (! $usuario->exists) {
            $usuario->forceFill(['name' => 'Cliente Demo', 'email' => 'cliente.demo@example.com', 'password' => Hash::make('Cliente123!')])->save();
        }
        $usuario->forceFill(['empresa_id' => $empresa->id, 'estado' => true])->save();
        $usuario->syncRoles(['Cliente']);

        // El cobrador demo recibe los edificios para su ruta de cobranza.
        $this->call(CobradorDemoSeeder::class);
    }

    private function usuario(string $username, string $nombre, string $correo, string $clave, string $rol, array $vinculos): void
    {
        $usuario = User::firstOrNew(['username' => $username]);
        if (! $usuario->exists) {
            $usuario->forceFill(['name' => $nombre, 'email' => $correo, 'password' => Hash::make($clave)]);
        }
        $usuario->forceFill($vinculos + ['estado' => true])->save();
        $usuario->syncRoles([$rol]);
    }
}
