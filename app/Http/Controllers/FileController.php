<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;

class FileController extends Controller
{

public function show($folder, $filename) {
    $path = storage_path("app/public/$folder/$filename");

    if (!file_exists($path)) return response('No encontrado', 404);

    return response()->file($path, [
        'Access-Control-Allow-Origin' => 'http://localhost:4200',
        'Access-Control-Allow-Methods' => 'GET'
    ]);
}    
}
