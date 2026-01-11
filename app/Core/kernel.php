<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define el schedule de tareas
     */
    protected function schedule(Schedule $schedule)
    {
        // Ejecuta tu comando pagos:actualizar cada minuto
        $schedule->command('pagos:actualizar')->everyMinute();
    }

    /**
     * Registra los comandos de Artisan
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
    }
}
