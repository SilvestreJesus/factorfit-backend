<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\PagoController;

class ActualizarPagos extends Command
{
    protected $signature = 'pagos:actualizar';
    protected $description = 'Actualizar status y recargos de pagos automáticamente';

    public function handle()
    {
        app(PagoController::class)->actualizarEstadosYRecargos();
        $this->info('Estados y recargos actualizados correctamente.');
    }
}
