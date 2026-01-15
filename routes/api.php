<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    UsuarioController, PagoController, PersonalController, 
    EventosController, EntrenamientosController, InstalacionesController, 
    AsistenciaController, WhatsAppController, PromocionController
};

/*
|--------------------------------------------------------------------------
| API ROUTES - FACTOR FIT
|--------------------------------------------------------------------------
*/

// ---------- AUTENTICACIÓN Y PERFIL ----------
Route::post('/login', [UsuarioController::class, 'login']);
Route::post('/recuperar-password', [UsuarioController::class, 'recuperarPassword']);
Route::put('/usuarios/perfil/{clave}', [UsuarioController::class, 'actualizarPerfil']);

// ---------- USUARIOS ----------
// ---------- USUARIOS ----------
Route::prefix('usuarios')->group(function () {
    // 1. RUTAS ESTÁTICAS (SIEMPRE PRIMERO)
    Route::get('/conteos-bitacora', [UsuarioController::class, 'obtenerConteosBitacora']);
    Route::get('/cambios-hoy', [UsuarioController::class, 'contarCambiosHoy']);
    Route::get('/por-sede', [UsuarioController::class, 'usuariosPorSede']);
    Route::get('/renovacion', [UsuarioController::class, 'usuariosParaRenovacion']);
    Route::get('/obtener-clientes-activos-sede', [UsuarioController::class, 'obtenerClientesActivosSede']);
    Route::get('/buscar/general/{texto}', [UsuarioController::class, 'buscarUsuarios']);
    Route::get('/buscar/sede', [UsuarioController::class, 'buscarUsuariosPorSede']);
    Route::get('/', [UsuarioController::class, 'index']);

    // 2. RUTAS CON PARÁMETROS (DESPUÉS)
    Route::post('/', [UsuarioController::class, 'store']);
    Route::post('/{clave}/subir-foto', [UsuarioController::class, 'subirFoto']);
    
    Route::get('/usuarios/{clave_usuario}', [UsuarioController::class, 'show']);
    Route::put('/{clave_usuario}', [UsuarioController::class, 'update']);
    Route::put('/{clave}/eliminar', [UsuarioController::class, 'eliminarUsuario']);
    Route::delete('/{clave_usuario}', [UsuarioController::class, 'destroy']);
    Route::delete('/permanente/{clave}', [UsuarioController::class, 'eliminarUsuarioPermanente']);
    Route::post('/{clave_usuario}/destruir-imagen', [UsuarioController::class, 'destruirImagen']);
});



// ---------- PAGOS ----------
Route::get('/pagos/actualizar', [PagoController::class, 'actualizarPagos']);
Route::get('/pagos/bitacora', [PagoController::class, 'bitacora']);
Route::get('/pagos/bitacora-recuperacion', [PagoController::class, 'bitacoraRecuperacion']);
Route::resource('pagos', PagoController::class)->except(['create', 'edit']);
Route::get('/buscar/pagos/{texto}', [PagoController::class, 'buscar']);

// ---------- PERSONAL ----------
Route::post('personal/destruir-imagen', [PersonalController::class, 'destruirImagen']);
Route::resource('personal', PersonalController::class)
    ->parameters(['personal' => 'clave'])
    ->except(['create', 'edit']);


// ---------- ASISTENCIAS ----------
Route::post('/asistencias/verificar-rostro', [AsistenciaController::class, 'verificarRostro']);
Route::get('/asistencias/reporte-mensual', [AsistenciaController::class, 'reporteMensual']);
Route::get('/asistencias/mes/{clave}/{year}/{month}', [AsistenciaController::class, 'getAsistenciasMes']);
Route::resource('asistencias', AsistenciaController::class)->except(['create', 'edit']);

// ---------- CONTENIDO (EVENTOS, ENTRENAMIENTOS, INSTALACIONES) ----------

// EVENTOS
Route::post('eventos/destruir-imagen', [EventosController::class, 'destruirImagen']);
Route::get('/buscar/eventos/{texto}', [EventosController::class, 'buscar']);
Route::resource('eventos', EventosController::class)
    ->parameters(['eventos' => 'clave'])
    ->except(['create', 'edit']);


// INSTALACIONES
Route::post('instalaciones/destruir-imagen', [InstalacionesController::class, 'destruirImagen']);
Route::get('/buscar/instalaciones/{texto}', [InstalacionesController::class, 'buscar']);
Route::resource('instalaciones', InstalacionesController::class)
    ->parameters(['instalaciones' => 'clave'])
    ->except(['create', 'edit']);



// ENTRENAMIENTOS
Route::post('entrenamientos/destruir-imagen', [EntrenamientosController::class, 'destruirImagen']);
Route::get('/buscar/entrenamientos/{texto}', [EntrenamientosController::class, 'buscar']);
Route::resource('entrenamientos', EntrenamientosController::class)
    ->parameters(['entrenamientos' => 'clave'])
    ->except(['create', 'edit']);



// ---------- OTROS SERVICIOS ----------
Route::post('/enviar-correo', [UsuarioController::class, 'enviarCorreo']);
Route::post('/whatsapp/masivo', [WhatsAppController::class, 'envioMasivo']);
Route::resource('promociones', PromocionController::class)->only(['index', 'store', 'destroy']);