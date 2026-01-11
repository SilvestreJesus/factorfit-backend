<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
});

// Registrar tu comando
Artisan::command('pagos:actualizar', function () {
    app(\App\Http\Controllers\PagoController::class)->actualizarEstadosYRecargos();
})->describe('Actualizar status y recargos de pagos');
