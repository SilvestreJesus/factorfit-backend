<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $query = Usuario::with(['pagos', 'asistencias']);

        $query->whereNotIn('rol', ['admin', 'superadmin']);


        if ($request->has('sede')) {
            $query->where('sede', $request->sede);
        }

        return response()->json($query->get());
    }




    public function show($clave_usuario)
    {
        $usuario = Usuario::with(['pagos', 'asistencias'])
            ->where('clave_usuario', $clave_usuario)
            ->first();

        return $usuario
            ? response()->json($usuario)
            : response()->json(['message' => 'Usuario no encontrado'], 404);
    }


    // POST /api/usuarios
public function store(Request $request)
{
    // 1. Validación: Ahora aceptamos qr_imagen como un string (la URL de Cloudinary)
    $validated = $request->validate([
        'nombres'            => 'required|string|max:40',
        'apellidos'          => 'required|string|max:40',
        'fecha_nacimiento'   => 'required|date',
        'telefono'           => 'required|string|max:15',
        'email'              => 'required|email|unique:usuarios,email',
        'password'           => 'required|string|min:6',
        'sede'               => 'nullable|string|max:30',
        'status'             => 'nullable|string|max:30',
        'ruta_imagen'        => 'nullable|string', // URL enviada desde Angular
        'qr_imagen'          => 'nullable|string', // URL enviada desde Angular
        'rol'                => 'nullable|string|max:30',
        'peso_inicial'       => 'required|string|max:12'
    ]);

    $validated['nombres'] = strtolower($validated['nombres']);
    $validated['apellidos'] = strtolower($validated['apellidos']);
    $validated['status'] = $validated['status'] ?? 'sin asignar';
    $validated['sede']   = $validated['sede'] ?? 'ninguno';
    $validated['password'] = bcrypt($validated['password']);

    $usuario = null;

    try {
        DB::transaction(function() use (&$usuario, $validated) {
            // Generar Clave de Usuario (CLI001...)
            $lastNumber = DB::table('usuarios')
                ->selectRaw("MAX(CAST(SUBSTRING(clave_usuario FROM 4) AS INTEGER)) AS max_num")
                ->value('max_num');

            $newNumber = $lastNumber ? $lastNumber + 1 : 1;
            $validated['clave_usuario'] = 'CLI' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);

            // Creamos el usuario con las URLs que vienen de Angular
            $usuario = Usuario::create($validated);
        });

        return response()->json([
            'message' => 'Usuario registrado con éxito',
            'usuario' => $usuario
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al registrar usuario en la base de datos',
            'error' => $e->getMessage()
        ], 500);
    }
}





    public function update(Request $request, $clave)
    {
        // Buscar usuario por clave_usuario
        $usuario = Usuario::where('clave_usuario', $clave)->first();

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        // Validaciones SIN PASSWORD
        $validated = $request->validate([
            'nombres'          => 'sometimes|string|max:60',
            'apellidos'        => 'sometimes|string|max:60',
            'fecha_nacimiento' => 'sometimes|date',
            'telefono'         => 'sometimes|string|max:35',
            'email'            => 'sometimes|email|unique:usuarios,email,' . $usuario->clave_usuario . ',clave_usuario',
            'sede'             => 'sometimes|string|max:30',
            'status'           => 'sometimes|string|max:30',
            'rol'              => 'sometimes|string|max:30',
            'peso_inicial'     => 'sometimes|string|max:25',
            'ruta_imagen'      => 'nullable|string',
            'qr_imagen'        => 'nullable|string',
            'fecha_inscripcion' => 'nullable|date_format:Y-m-d H:i:s',
            'fecha_corte'       => 'nullable|date_format:Y-m-d H:i:s',
            'tipo_pago'         => 'nullable|string'
        ]);

        unset($validated['password']);

        // Actualizar usuario sin tocar contraseña
        $usuario->update($validated);

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'usuario' => $usuario
        ]);
    }



    public function destroy($clave_usuario)
    {
        // Buscar usuario por clave
        $usuario = Usuario::where('clave_usuario', $clave_usuario)->first();

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

            // ---- ELIMINAR FOTO DE CLOUDINARY ----
        if ($usuario->ruta_imagen) {
            $publicIdFoto = $this->getPublicIdFromUrl($usuario->ruta_imagen);
            if ($publicIdFoto) {
                Cloudinary::destroy($publicIdFoto);
            }
        }

        // ---- ELIMINAR QR DE CLOUDINARY ----
        if ($usuario->qr_imagen) {
            $publicIdQr = $this->getPublicIdFromUrl($usuario->qr_imagen);
            if ($publicIdQr) {
                Cloudinary::destroy($publicIdQr);
            }
        }

        // ---- ELIMINAR REGISTRO ----
        $usuario->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }

// Búsqueda con filtros
    public function buscar($texto)
    {
        $usuarios = Usuario::where('clave_usuario', 'LIKE', "%$texto%")
            ->orWhere('nombres', 'LIKE', "%$texto%")
            ->orWhere('apellidos', 'LIKE', "%$texto%")
            ->orWhere('telefono', 'LIKE', "%$texto%")
            ->orWhere('email', 'LIKE', "%$texto%")
            ->take(10)
            ->get();

        return response()->json($usuarios);
    }

    // Búsqueda general por texto
    public function buscarUsuarios(Request $request)
    {
        $texto = $request->input('texto');

        $usuarios = Usuario::where('clave_usuario', 'LIKE', "%$texto%")
            ->orWhere('nombres', 'LIKE', "%$texto%")
            ->orWhere('apellidos', 'LIKE', "%$texto%")
            ->orWhere('telefono', 'LIKE', "%$texto%")
            ->orWhere('email', 'LIKE', "%$texto%")
            ->orWhere('status', 'LIKE', "%$texto%")
            ->orWhere('rol', 'LIKE', "%$texto%")
            ->get();

        return response()->json($usuarios);
    }


    public function actualizarPerfil(Request $request, $clave)
{
    // Buscamos por la columna clave_usuario que vimos en tu \d usuarios
    $usuario = Usuario::where('clave_usuario', $clave)->first();

    if (!$usuario) {
        return response()->json(['message' => 'Usuario no encontrado'], 404);
    }

    $validated = $request->validate([
        'nombres'   => 'sometimes|string|max:40',
        'apellidos' => 'sometimes|string|max:40',
        'email'     => 'sometimes|email|unique:usuarios,email,' . $usuario->clave_usuario . ',clave_usuario',
        'telefono'  => 'sometimes|string|max:15',
        'password'  => 'nullable|string|min:6',
        'ruta_imagen' => 'nullable|string'
    ]);

    // Procesar contraseña solo si se envió una nueva
    if (!empty($request->password)) {
        $validated['password'] = bcrypt($request->password);
    } else {
        unset($validated['password']);
    }

    $usuario->update($validated);

    return response()->json([
        'message' => 'Perfil actualizado correctamente',
        'usuario' => $usuario
    ]);
}


public function login(Request $request)
{
    $request->validate([
        'email' => 'required|email',
        'password' => 'required|string'
    ]);

    $usuario = Usuario::where('email', $request->email)->first();

    // 1. Validar existencia y contraseña
    if (!$usuario || !Hash::check($request->password, $usuario->password)) {
        return response()->json(['message' => 'Correo o contraseña incorrectos'], 401);
    }

    // 2. Definir estados permitidos y prohibidos
    $estadosPermitidos = ['activo', 'inactivo', 'pendiente', 'proximo a vencer'];
    $statusActual = strtolower($usuario->status);

    // 3. Validar el status del usuario
    if ($statusActual === 'eliminado') {
        return response()->json(['message' => 'Esta cuenta ha sido eliminada y no puede acceder.'], 403);
    }

    if ($statusActual === 'sin asignar') {
        return response()->json(['message' => 'Tu cuenta aún no tiene un estado asignado. Contacta al administrador.'], 403);
    }

    // Validación general por si el estado no está en la lista permitida
    if (!in_array($statusActual, $estadosPermitidos)) {
        return response()->json(['message' => 'No puedes iniciar sesión. Estado actual: ' . $usuario->status], 403);
    }

    // 4. Si todo es correcto, devolver respuesta
    return response()->json([
        'message' => 'Inicio de sesión correcto',
        'usuario' => $usuario,
        'rol' => $usuario->rol
    ], 200);
}

    public function eliminarUsuario($clave)
    {
        $usuario = Usuario::where('clave_usuario', $clave)->first();

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $usuario->status = 'eliminado';
        $usuario->save();

        return response()->json(['message' => 'Usuario marcado como eliminado']);
    }

    public function usuariosPorSede(Request $request)
    {
        $sede = $request->query('sede');

        $query = Usuario::query();

        // Excluir admins
        $query->whereNotIn('rol', ['admin', 'admin1', 'admin2', 'superadmin']);

        // No mostrar eliminados
        $query->where(function($q){
            $q->whereNull('status')
            ->orWhere('status', '!=', 'eliminado');
        });

        // FILTRO DE SEDE CORRECTO
        if ($sede) {
            $query->where(function ($q) use ($sede) {
                $q->where('sede', $sede)
                ->orWhereNull('sede')
                ->orWhere('sede', '')
                ->orWhere('sede', 'ninguno');
            });
        }
        $query->orderBy('updated_at', 'desc');
        return $query->get();
    }

// UsuarioController.php

public function usuariosParaRenovacion(Request $request)
{
    $sede = $request->query('sede');
    $query = Usuario::query();

    // 1. Excluir siempre a los administradores
    $query->whereNotIn('rol', ['admin', 'admin1', 'admin2', 'superadmin']);

    // 2. Filtrar por sede
    if ($sede) {
        $query->where('sede', $sede);
    }

    // Nota: Aquí NO filtramos por status != 'eliminado', 
    // así que los borrados llegarán al frontend.
    
    return response()->json($query->get());
}


    public function buscarUsuariosPorSede(Request $request)
    {
        $texto = $request->input('texto');
        $sede = $request->input('sede');

        $usuarios = Usuario::where('sede', $sede)
            ->whereNotIn('rol', ['admin', 'admin1', 'admin2', 'superadmin'])
            ->where(function($q) use ($texto) {
                $q->where('clave_usuario', 'LIKE', "%$texto%")
                ->orWhere('nombres', 'LIKE', "%$texto%")
                ->orWhere('apellidos', 'LIKE', "%$texto%")
                ->orWhere('telefono', 'LIKE', "%$texto%")
                ->orWhere('email', 'LIKE', "%$texto%");
            })
            ->take(10)
            ->get();
            

        return response()->json($usuarios);
    }


public function subirFoto(Request $request, $clave)
{
    // Ahora solo validamos que llegue un string (la URL)
    $request->validate([
        'ruta_imagen' => 'required|string'
    ]);

    $usuario = Usuario::where('clave_usuario', $clave)->firstOrFail();
    $usuario->ruta_imagen = $request->ruta_imagen;
    $usuario->save();

    return response()->json([
        'message' => 'Ruta guardada correctamente',
        'ruta_imagen' => $usuario->ruta_imagen
    ]);
}


public function enviarCorreo(Request $request)
{
    $data = $request->validate([
        'emails'    => 'required|array',
        'asunto'    => 'required|string',
        'mensaje'   => 'required|string',
        'sede'      => 'nullable|string',
        'imagen'    => 'nullable|string', 
    ]);

    try {
        $emails = $data['emails'];

        foreach ($emails as $destinatario) {
            // Pasamos $data a la vista
            Mail::send('emails.formal', $data, function ($message) use ($data, $destinatario) {
                $message->to($destinatario)->subject($data['asunto']);
            });
        }

        return response()->json(['message' => 'Correos enviados con éxito'], 200);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

public function recuperarPassword(Request $request)
{
    $request->validate(['email' => 'required|email']);
    $usuario = Usuario::where('email', $request->email)->first();

    if (!$usuario) {
        return response()->json(['message' => 'El correo electrónico no está registrado.'], 404);
    }

    $passwordTemporal = str_replace(['/', '+', '='], '', base64_encode(random_bytes(6)));

    try {
        $usuario->password = bcrypt($passwordTemporal);
        $usuario->save();

        // --- IMPORTANTE: EL NOMBRE DE LA VISTA DEBE SER EXACTO ---
        // Según tu captura de VS Code, el archivo es 'formal_recuperacion.blade.php'
        $htmlProcesado = view('emails.formal_recuperacion', [
            'nombres'  => ucwords($usuario->nombres),
            'mensaje'  => 'Hemos recibido una solicitud para renovar tu contraseña. Usa la siguiente clave temporal para entrar al sistema:',
            'password' => $passwordTemporal,
            'sede'     => $usuario->sede ?? 'Emiliano'
        ])->render();

        $urlCorreos = 'https://corrreoservicio-production.up.railway.app/enviar-correo';
        
        $response = \Illuminate\Support\Facades\Http::post($urlCorreos, [
            'emails'      => [$usuario->email],
            'asunto'      => 'Recuperación de Acceso - Factor Fit',
            'htmlDirecto' => $htmlProcesado, // Enviamos tu diseño morado
            'tipo'        => 'html_puro'    // Usamos 'html_puro' para que Node NO meta su diseño
        ]);

        if ($response->successful()) {
            return response()->json(['message' => 'Se ha enviado una nueva contraseña a tu correo.']);
        } 
        
        return response()->json(['error' => 'Error al enviar el correo vía Node'], 500);

    } catch (\Exception $e) {
        return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
    }
}

public function obtenerClientesActivosSede(Request $request)
{
    $sede = $request->query('sede');
    $query = Usuario::query();

    // 1. Solo roles tipo 'cliente'
    $query->where('rol', 'cliente');

    // 2. Que NO estén marcados como eliminados
    $query->where(function($q){
        $q->whereNull('status')
          ->orWhere('status', '!=', 'eliminado');
    });

    // 3. Filtro de sede
    if ($sede && $sede !== 'ninguno') {
        $query->where('sede', $sede);
    }

    return response()->json($query->get());
}






public function eliminarUsuarioPermanente($clave)
{
    $usuario = Usuario::where('clave_usuario', $clave)->first();
    
    if (!$usuario) {
        return response()->json(['message' => 'Usuario no encontrado'], 404);
    }

    try {
        // 1. Borrar Foto de Perfil de Cloudinary
        if ($usuario->ruta_imagen) {
            $this->borrarImagenCloudinary($usuario->ruta_imagen);
        }

        // 2. Borrar QR de Cloudinary (NUEVO)
        if ($usuario->qr_imagen) {
            $this->borrarImagenCloudinary($usuario->qr_imagen);
        }

        // 3. Eliminar el registro de la DB
        $usuario->delete(); 

        return response()->json(['message' => 'Usuario, foto y QR eliminados permanentemente']);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al eliminar',
            'error' => $e->getMessage()
        ], 500);
    }
}

private function getPublicIdFromUrl($url)
{
    // Esta lógica es más segura para extraer el public_id de Cloudinary
    // Ejemplo: .../image/upload/v12345/usuarios/qrs/archivo.png -> usuarios/qrs/archivo
    try {
        $path = parse_url($url, PHP_URL_PATH);
        $segments = explode('/', $path);
        
        // Buscamos dónde empieza el path real después de 'upload' y la 'versión' (v123...)
        $uploadIndex = array_search('upload', $segments);
        if ($uploadIndex !== false) {
            // Saltamos 'upload' y el siguiente segmento que suele ser la versión (v123456)
            $relevantSegments = array_slice($segments, $uploadIndex + 2);
            $publicIdWithExt = implode('/', $relevantSegments);
            
            // Quitamos la extensión (.jpg, .png, etc)
            return preg_replace('/\.[^.]+$/', '', $publicIdWithExt);
        }
    } catch (\Exception $e) {
        return null;
    }
    return null;
}


/**
 * Endpoint para borrar solo una imagen (útil cuando se cambia de foto sin borrar al usuario)
 */
public function destruirImagen(Request $request)
{
    $url = $request->input('url');
    if ($url && str_contains($url, 'cloudinary.com')) {
        $this->borrarImagenCloudinary($url);
        return response()->json(['message' => 'Imagen procesada para eliminación']);
    }
    return response()->json(['message' => 'URL no válida para Cloudinary'], 400);
}

/**
 * Lógica privada para interactuar con el API de Cloudinary
 */
private function borrarImagenCloudinary($url)
{
    try {
        $publicId = $this->getPublicIdFromUrl($url);
        if ($publicId) {
            Cloudinary::destroy($publicId);
            \Log::info("Cloudinary: Imagen borrada con ID: " . $publicId);
        }
    } catch (\Exception $e) {
        \Log::error("Falla al borrar en Cloudinary: " . $e->getMessage());
    }
}


public function obtenerConteosBitacora(Request $request)
{
    $sede = $request->query('sede');
    $hoy = now()->startOfDay();

    // Pagos nuevos (Ingresos)
    $pagos = \App\Models\Pago::whereHas('usuario', function($q) use ($sede) {
                    $q->where('sede', $sede);
                })
                ->where('created_at', '>=', $hoy)->count();

    // Asistencias nuevas
    $asistencias = \App\Models\Asistencia::whereHas('usuario', function($q) use ($sede) {
                        $q->where('sede', $sede);
                    })
                    ->where('created_at', '>=', $hoy)->count();

    // Renovaciones (Usuarios actualizados hoy)
    $renovacion = \App\Models\Usuario::where('sede', $sede)
                ->where('updated_at', '>=', $hoy)
                ->count();

    return response()->json([
        'pagos' => $pagos,
        'asistencias' => $asistencias,
        'renovacion' => $renovacion, // Cambiado de 'usuarios' a 'renovacion' para hacer match
        'total' => $pagos + $asistencias + $renovacion
    ]);
}


public function contarCambiosHoy(Request $request)
{
    $sede = $request->query('sede');
    $hoy = now()->format('Y-m-d');
    
    // Recibimos cuánto ha visto el usuario desde el Header de Angular
    $vistos = $request->query('vistos', 0); 

    $totalBaseDatos = \App\Models\Usuario::where('sede', $sede)
        ->whereNotIn('rol', ['admin', 'superadmin']) 
        ->where(function($query) use ($hoy) {
            $query->whereDate('created_at', $hoy)
                  ->orWhereDate('updated_at', $hoy);
        })
        ->count();

    // El resultado para el badge es el total menos lo que ya se vio
    $totalFinal = $totalBaseDatos - $vistos;

    return response()->json([
        'total' => $totalFinal > 0 ? $totalFinal : 0
    ]);
}
}
