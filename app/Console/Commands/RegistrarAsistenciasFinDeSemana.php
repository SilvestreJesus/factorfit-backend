<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Usuario;
use App\Models\Asistencia;
use Carbon\Carbon;

class RegistrarAsistenciasFinDeSemana extends Command
{
    // Nombre del comando para ejecutarlo manualmente si quieres
    protected $signature = 'asistencia:fines-semana';
    protected $description = 'Registra asistencia automática para clientes los sábados y domingos';

    public function handle()
    {
        $hoy = Carbon::now();

        // Solo actuar si es Sábado (6) o Domingo (0)
        if ($hoy->isWeekend()) {
            $clientes = Usuario::where('rol', 'cliente')
                ->where('status', '!=', 'eliminado')
                ->get();

            $conteo = 0;

            foreach ($clientes as $cliente) {
                // Verificar si ya existe (por si acaso se ejecuta dos veces)
                $existe = Asistencia::where('clave_cliente', $cliente->clave_usuario)
                    ->whereDate('fecha_diario', $hoy->toDateString())
                    ->exists();

                if (!$existe) {
                    Asistencia::create([
                        'clave_cliente' => $cliente->clave_usuario,
                        'fecha_diario'  => $hoy,
                        'porcentaje'    => '100%', // Asistencia automática
                    ]);
                    $conteo++;
                }
            }

            $this->info("Se registraron $conteo asistencias automáticas de fin de semana.");
        } else {
            $this->info("Hoy no es fin de semana, no se realizó ninguna acción.");
        }
    }
}