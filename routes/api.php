<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PersonalController;
use App\Http\Controllers\EventosController;
use App\Http\Controllers\EntrenamientosController;
use App\Http\Controllers\InstalacionesController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Middleware\CorsMiddleware;
use Illuminate\Support\Facades\File; 
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\PromocionController;

/*
|--------------------------------------------------------------------------
| API RUTAS
|--------------------------------------------------------------------------
*/

// ---------- USUARIOS ----------

Route::get('/usuarios', [UsuarioController::class, 'index']);
Route::get('/usuarios/por-sede', [UsuarioController::class, 'usuariosPorSede']);

Route::get('/usuarios/{clave_usuario}', [UsuarioController::class, 'show']);
Route::post('/usuarios', [UsuarioController::class, 'store']);
Route::put('/usuarios/perfil/{clave}', [UsuarioController::class, 'actualizarPerfil']);
Route::put('/usuarios/{clave_usuario}', [UsuarioController::class, 'update']);
Route::delete('/usuarios/{clave_usuario}', [UsuarioController::class, 'destroy']);
Route::put('/usuarios/{clave}/eliminar', [UsuarioController::class, 'eliminarUsuario']);
Route::get('/usuarios/buscar/general/{texto}', [UsuarioController::class, 'buscarUsuarios']);
Route::get('/usuarios/buscar/sede', [UsuarioController::class, 'buscarUsuariosPorSede']);
Route::post('/usuarios/{clave}/subir-foto', [UsuarioController::class, 'subirFoto']);
Route::get('/storage/{folder}/{filename}', [FileController::class, 'show']);
Route::post('/enviar-correo', [UsuarioController::class, 'enviarCorreo']);
Route::post('/recuperar-password', [UsuarioController::class, 'recuperarPassword']);
Route::get('/usuarios/obtener-clientes-activos-sede', [UsuarioController::class, 'obtenerClientesActivosSede']);
Route::delete('/usuarios/{clave}/eliminar-permanente', [UsuarioController::class, 'eliminarUsuarioPermanente']);
Route::post('/login', [UsuarioController::class, 'login']);
Route::get('/qr/{filename}', function ($filename) {
    $path = storage_path("app/public/qr/$filename");

    if (!File::exists($path)) {
        return response('Archivo no encontrado', 404)
               ->header("Access-Control-Allow-Origin", "http://localhost:4200");
    }

    $file = File::get($path);
    $type = File::mimeType($path);

    return response($file, 200)
           ->header("Content-Type", $type)
           ->header("Access-Control-Allow-Origin", "http://localhost:4200");
});

// Ruta para DESCARGAR (Forzada)
Route::get('/qr-download/{filename}', function ($filename) {
    $path = storage_path("app/public/qr/$filename");

    if (!File::exists($path)) {
        return response()->json(['message' => 'Archivo no encontrado'], 404);
    }

    // response()->download hace toda la magia de los headers por ti
    return response()->download($path, $filename, [
        'Access-Control-Allow-Origin' => 'http://localhost:4200',
        'Access-Control-Expose-Headers' => 'Content-Disposition'
    ]);
});
Route::post('/whatsapp/masivo', [WhatsAppController::class, 'envioMasivo']);
Route::get('/pagos/actualizar', [PagoController::class, 'actualizarPagos']);

// ---------- PAGOS ----------
// ---------- PAGOS ----------
Route::get('/pagos', [PagoController::class, 'index']);
Route::get('/pagos/bitacora', [PagoController::class, 'bitacora']);
Route::get('/pagos/bitacora-recuperacion', [PagoController::class, 'bitacoraRecuperacion']);
Route::get('/pagos/{clave_cliente}', [PagoController::class, 'show']);
Route::post('/pagos', [PagoController::class, 'store']);
Route::put('/pagos/{clave_cliente}', [PagoController::class, 'update']);
Route::delete('/pagos/{clave_cliente}', [PagoController::class, 'destroy']);
Route::get('/buscar/pagos/{texto}', [PagoController::class, 'buscar']);

// ---------- PERSONAL ----------

Route::get('/personal', [PersonalController::class, 'index']);
Route::get('/personal/{id}', [PersonalController::class, 'show']);
Route::post('/personal', [PersonalController::class, 'store']);

Route::put('/personal/{clave_personal}', [PersonalController::class, 'update']);
Route::delete('/personal/{clave_personal}', [PersonalController::class, 'destroy']);

Route::get('/buscar/personal/{texto}', [PersonalController::class, 'buscar']);

// ---------- ASISTENCIAS ----------
Route::get('/asistencias', [AsistenciaController::class, 'index']);
Route::get('/asistencias/reporte-mensual', [AsistenciaController::class, 'reporteMensual']); // <--- Esta es la buena
Route::get('/asistencias/{clave_cliente}', [AsistenciaController::class, 'show']);
Route::post('/asistencias', [AsistenciaController::class, 'store']);
Route::put('/asistencias/{clave_cliente}', [AsistenciaController::class, 'update']);
Route::delete('/asistencias/{clave_cliente}', [AsistenciaController::class, 'destroy']);
Route::get('/buscar/asistencias/{texto}', [AsistenciaController::class, 'buscar']);
Route::get('/asistencias/mes/{clave}/{year}/{month}', [AsistenciaController::class, 'getAsistenciasMes']);



// ---------- EVENTOS ----------

Route::get('/eventos', [EventosController::class, 'index']);
Route::post('/eventos', [EventosController::class, 'store']);
Route::get('/eventos/{id}', [EventosController::class, 'show']);
Route::put('/eventos/{clave_eventos}', [EventosController::class, 'update']);
Route::delete('/eventos/{clave_eventos}', [EventosController::class, 'destroy']);
Route::get('/buscar/eventos/{texto}', [EventosController::class, 'buscar']);


// ---------- ENTRENAMIENTOS ----------

Route::get('/entrenamientos', [EntrenamientosController::class, 'index']);
Route::post('/entrenamientos', [EntrenamientosController::class, 'store']);
Route::get('/entrenamientos/{id}', [EntrenamientosController::class, 'show']);
Route::put('/entrenamientos/{clave_entrenamientos}', [EntrenamientosController::class, 'update']);
Route::delete('/entrenamientos/{clave_entrenamientos}', [EntrenamientosController::class, 'destroy']);
Route::get('/buscar/entrenamientos/{texto}', [EntrenamientosController::class, 'buscar']);

// ---------- INSTALACIONES ----------

Route::get('/instalaciones', [InstalacionesController::class, 'index']);
Route::post('/instalaciones', [InstalacionesController::class, 'store']);
Route::get('/instalaciones/{id}', [InstalacionesController::class, 'show']);
Route::put('/instalaciones/{clave_instalaciones}', [InstalacionesController::class, 'update']);
Route::delete('/instalaciones/{clave_instalaciones}', [InstalacionesController::class, 'destroy']);
Route::get('/buscar/instalaciones/{texto}', [InstalacionesController::class, 'buscar']);


// routes/api.php
Route::get('/usuarios/renovacion', [UsuarioController::class, 'usuariosParaRenovacion']);

Route::get('/promociones', [PromocionController::class, 'index']);
Route::post('/promociones', [PromocionController::class, 'store']);
Route::delete('/promociones/{id}', [PromocionController::class, 'destroy']);