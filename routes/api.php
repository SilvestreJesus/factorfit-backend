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
Route::prefix('usuarios')->group(function () {
    Route::get('/', [UsuarioController::class, 'index']);
    Route::get('/por-sede', [UsuarioController::class, 'usuariosPorSede']);
    Route::get('/renovacion', [UsuarioController::class, 'usuariosParaRenovacion']);
    Route::get('/obtener-clientes-activos-sede', [UsuarioController::class, 'obtenerClientesActivosSede']);
    Route::get('/buscar/general/{texto}', [UsuarioController::class, 'buscarUsuarios']);
    Route::get('/buscar/sede', [UsuarioController::class, 'buscarUsuariosPorSede']);
    
    Route::post('/', [UsuarioController::class, 'store']);
    Route::post('/{clave}/subir-foto', [UsuarioController::class, 'subirFoto']);
    
    Route::get('/{clave_usuario}', [UsuarioController::class, 'show']);
    Route::put('/{clave_usuario}', [UsuarioController::class, 'update']);
    Route::put('/{clave}/eliminar', [UsuarioController::class, 'eliminarUsuario']); // Eliminación lógica (status)
    Route::delete('/{clave_usuario}', [UsuarioController::class, 'destroy']); // Eliminación física con limpieza Cloudinary
    Route::delete('/{clave}/eliminar-permanente', [UsuarioController::class, 'eliminarUsuarioPermanente']);
});

// ---------- PAGOS ----------
Route::get('/pagos/actualizar', [PagoController::class, 'actualizarPagos']);
Route::get('/pagos/bitacora', [PagoController::class, 'bitacora']);
Route::get('/pagos/bitacora-recuperacion', [PagoController::class, 'bitacoraRecuperacion']);
Route::resource('pagos', PagoController::class)->except(['create', 'edit']);
Route::get('/buscar/pagos/{texto}', [PagoController::class, 'buscar']);

// ---------- PERSONAL ----------
Route::resource('personal', PersonalController::class)->except(['create', 'edit']);
Route::get('/buscar/personal/{texto}', [PersonalController::class, 'buscar']);

// ---------- ASISTENCIAS ----------
Route::post('/asistencias/verificar-rostro', [AsistenciaController::class, 'verificarRostro']);
Route::get('/asistencias/reporte-mensual', [AsistenciaController::class, 'reporteMensual']);
Route::get('/asistencias/mes/{clave}/{year}/{month}', [AsistenciaController::class, 'getAsistenciasMes']);
Route::resource('asistencias', AsistenciaController::class)->except(['create', 'edit']);

// ---------- CONTENIDO (EVENTOS, ENTRENAMIENTOS, INSTALACIONES) ----------
Route::resource('eventos', EventosController::class)->except(['create', 'edit']);
Route::get('/buscar/eventos/{texto}', [EventosController::class, 'buscar']);

Route::resource('entrenamientos', EntrenamientosController::class)->except(['create', 'edit']);
Route::get('/buscar/entrenamientos/{texto}', [EntrenamientosController::class, 'buscar']);

Route::resource('instalaciones', InstalacionesController::class)->except(['create', 'edit']);
Route::get('/buscar/instalaciones/{texto}', [InstalacionesController::class, 'buscar']);

// ---------- OTROS SERVICIOS ----------
Route::post('/enviar-correo', [UsuarioController::class, 'enviarCorreo']);
Route::post('/whatsapp/masivo', [WhatsAppController::class, 'envioMasivo']);
Route::resource('promociones', PromocionController::class)->only(['index', 'store', 'destroy']);