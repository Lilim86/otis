<?php

namespace App\Console\Commands;

use App\Services\NotificacionesClienteService;
use Illuminate\Console\Command;

class AvisarClientes extends Command
{
    protected $signature = 'avisos:clientes {--simular : Solo cuenta lo que se enviaría}';

    protected $description = 'Avisa por correo a los clientes de visitas próximas y cuotas por vencer o vencidas';

    public function handle(NotificacionesClienteService $avisos): int
    {
        $r = $avisos->avisosDelDia((bool) $this->option('simular'));

        $this->info(($this->option('simular') ? '[simulación] ' : '')."Visitas: {$r['visitas']} · cuotas por vencer: {$r['cuotas_por_vencer']} · cuotas vencidas: {$r['cuotas_vencidas']}");

        return self::SUCCESS;
    }
}
