<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class Cors
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Permite Angular desde localhost:4200
        $response->header('Access-Control-Allow-Origin', 'http://localhost:4200');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, X-Auth-Token, Authorization, Origin');
        return $response;
    }
}
