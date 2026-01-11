<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // <--- IMPORTANTE: No olvides esta línea
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function envioMasivo(Request $request)
    {
        // 1. Recibimos los datos de Angular
        $usuarios = $request->input('usuarios');
        $mensajeBase = $request->input('mensaje');
        
        // 2. Configuración de tu Evolution API
        $apiUrl = "http://localhost:8080"; 
        $apiKey = "factor_fit_key";
        $instance = "FactorFit";

        $enviados = 0;
        $errores = 0;

        foreach ($usuarios as $user) {
            try {
                // Limpiar el teléfono: quitar espacios, signos + y guiones
                $telefono = preg_replace('/\D/', '', $user['telefono']);

                // Validación para México: asegurar prefijo 521 si tiene 10 dígitos
                if (strlen($telefono) == 10) {
                    $telefono = "521" . $telefono;
                }

                // Enviar a la Evolution API
                $response = Http::withHeaders([
                    'apikey' => $apiKey,
                    'Content-Type' => 'application/json'
                ])->post("$apiUrl/message/sendText/$instance", [
                    "number" => $telefono,
                    "textMessage" => [
                        "text" => $mensajeBase
                    ]
                ]);

                if ($response->successful()) {
                    $enviados++;
                } else {
                    $errores++;
                    Log::error("Error enviando a $telefono: " . $response->body());
                }

                // Pausa de 1 segundo entre mensajes para evitar baneos de WhatsApp
                sleep(1); 

            } catch (\Exception $e) {
                $errores++;
                Log::error("Excepción en envío masivo: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Proceso terminado',
            'enviados' => $enviados,
            'errores' => $errores
        ], 200);
    }
}