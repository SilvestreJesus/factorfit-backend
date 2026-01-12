<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class GlobalCors
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Si la respuesta es un archivo (como tus imágenes), usamos este método
        if (method_exists($response, 'header')) {
            return $response
                ->header('Access-Control-Allow-Origin', 'http://localhost:4200')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization');
        }

        return $response;
    }
}