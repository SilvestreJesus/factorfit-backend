<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;

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
        $validated = $request->validate([
            'nombres'            => 'required|string|max:40',
            'apellidos'          => 'required|string|max:40',
            'fecha_nacimiento'   => 'required|date',
            'telefono'           => 'required|string|max:15',
            'email'              => 'required|email|unique:usuarios,email',
            'password'           => 'required|string|min:6',
            'sede'               => 'nullable|string|max:30',
            'status'             => 'nullable|string|max:30',
            'ruta_imagen'        => 'nullable|string',
            'qr_imagen'          => 'nullable|string',
            'rol'                => 'nullable|string|max:30',
            'peso_inicial'       => 'required|string|max:8'
        ]);
        $validated['nombres'] = strtolower($validated['nombres']);
        $validated['apellidos'] = strtolower($validated['apellidos']);
        $validated['status'] = $validated['status'] ?? 'sin asignar';
        $validated['sede']   = $validated['sede'] ?? 'ninguno';
        $validated['password'] = bcrypt($validated['password']);

        $usuario = null;

        DB::transaction(function() use (&$usuario, $validated) {

            $lastNumber = DB::table('usuarios')
                ->selectRaw("MAX(CAST(SUBSTRING(clave_usuario FROM 4) AS INTEGER)) AS max_num")
                ->value('max_num');

            $newNumber = $lastNumber ? $lastNumber + 1 : 1;

            $validated['clave_usuario'] = 'CLI' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);

            $usuario = Usuario::create($validated);
        });

        // ---- EJECUTAR SCRIPT PYTHON PARA GENERAR QR ----
        $command = "python " . base_path("python/qr_generator.py") . " " . $usuario->clave_usuario;

        $qrPath = trim(shell_exec($command));

        $usuario->qr_imagen = $qrPath;
        $usuario->save();

        return response()->json([
            'message' => 'Usuario registrado y QR generado correctamente',
            'usuario' => $usuario
        ], 201);
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

        // ---- ELIMINAR FOTO ----
        if ($usuario->ruta_imagen) {
            $path = str_replace('storage/', '', $usuario->ruta_imagen);
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        // ---- ELIMINAR QR ----
        if ($usuario->qr_imagen) {
            $qrPath = str_replace('storage/', '', $usuario->qr_imagen);
            if (Storage::disk('public')->exists($qrPath)) {
                Storage::disk('public')->delete($qrPath);
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
    $request->validate([
        'foto' => 'required|image|max:2048'
    ]);

    $usuario = Usuario::where('clave_usuario', $clave)->firstOrFail();

    // Guardar imagen
    $path = $request->file('foto')->store('usuarios', 'public');

    // Eliminar foto anterior correctamente
    if ($usuario->ruta_imagen) {
        $oldPath = str_replace('storage/', '', $usuario->ruta_imagen);
        if (\Storage::disk('public')->exists($oldPath)) {
            \Storage::disk('public')->delete($oldPath);
        }
    }

    // Guardar solo la ruta interna
    $usuario->ruta_imagen = 'storage/' . $path;
    $usuario->save();

    return response()->json([
        'ruta_imagen' => $usuario->ruta_imagen
    ]);
}


public function enviarCorreo(Request $request)
{
    $data = $request->validate([
        'emails'    => 'required|array',
        'emails.*'  => 'email',
        'asunto'    => 'required|string',
        'mensaje'   => 'required|string',
        'sede'      => 'nullable|string',
        'imagen'    => 'nullable|string', // Base64
    ]);

    try {
        $emails = $data['emails'];
        
        // Pre-procesamos la imagen fuera del loop para ahorrar memoria
        $imageData = null;
        if (!empty($data['imagen'])) {
            // Extraer solo los datos base64 eliminando el prefijo "data:image/png;base64,"
            $image_parts = explode(";base64,", $data['imagen']);
            if (count($image_parts) > 1) {
                $imageData = base64_decode($image_parts[1]);
            }
        }

        foreach ($emails as $destinatario) {
            // Pasamos $imageData al closure
            Mail::send('emails.formal', $data, function ($message) use ($data, $destinatario, $imageData) {
                $message->to($destinatario)
                        ->subject($data['asunto']);
                
                // Si hay imagen, la inyectamos como variable especial para la vista
                if ($imageData) {
                    // Generamos el CID que usaremos en el HTML
                    $cid = $message->embedData($imageData, 'imagen_fitness.png', 'image/png');
                    // Inyectamos el CID globalmente para este envío
                    $data['cid_url'] = $cid; 
                }
            });
        }

        return response()->json(['message' => 'Correos enviados con éxito']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

public function recuperarPassword(Request $request)
{
    $request->validate(['email' => 'required|email']);

    // 1. Buscar si el correo existe
    $usuario = Usuario::where('email', $request->email)->first();

    if (!$usuario) {
        // Por seguridad, a veces es mejor decir que se envió el correo aunque no exista,
        // pero aquí devolveremos error para tu control interno.
        return response()->json(['message' => 'El correo electrónico no está registrado.'], 404);
    }

    // 2. Generar una contraseña temporal aleatoria (8 caracteres)
    $passwordTemporal = str_replace(['/', '+', '='], '', base64_encode(random_bytes(6)));

    try {
        // 3. Actualizar la contraseña en la base de datos (Encriptada)
        $usuario->password = bcrypt($passwordTemporal);
        $usuario->save();

        // 4. Preparar datos para el correo
        $data = [
            'email_destino' => $usuario->email,
            'asunto'        => 'Recuperación de Acceso - Factor Fit',
            'nombres'       => $usuario->nombres,
            'password'      => $passwordTemporal, // Enviamos la de texto plano al correo
            'mensaje'       => "Hemos recibido una solicitud para renovar tu contraseña. Tu nueva clave temporal de acceso es: "
        ];

        // Usamos la misma vista 'emails.formal' que ya tienes
        Mail::send('emails.formal_recuperacion', $data, function ($message) use ($data) {
            $message->to($data['email_destino'])
                    ->subject($data['asunto']);
        });

        return response()->json(['message' => 'Se ha enviado una nueva contraseña a tu correo.']);

    } catch (\Exception $e) {
        return response()->json(['error' => 'Error al procesar la recuperación: ' . $e->getMessage()], 500);
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

/**
 * Eliminación física de la base de datos por clave_usuario.
 */
public function eliminarUsuarioPermanente($clave)
{
    $usuario = Usuario::where('clave_usuario', $clave)->first();
    
    if (!$usuario) {
        return response()->json(['message' => 'Usuario no encontrado'], 404);
    }

    // Al usar delete(), se borra físicamente. 
    // Si tienes OnDelete('cascade') en la DB, se borrarán sus pagos.
    $usuario->delete(); 

    return response()->json(['message' => 'Usuario eliminado permanentemente del sistema']);
}

}
