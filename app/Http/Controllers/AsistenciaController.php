<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // Importante para pegarle a la API
use Illuminate\Support\Facades\File;
use Carbon\Carbon;


class AsistenciaController extends Controller
{
    /**
     * Obtener todas las asistencias con la información del usuario
     */

public function index(Request $request)
{
    try {
        $sede = $request->query('sede'); // Recibimos la sede del frontend

        // 1. Obtener registros filtrados por sede a través de la relación 'usuario'
        $query = Asistencia::with(['usuario.pago'])
            ->whereHas('usuario', function($q) use ($sede) {
                if ($sede && $sede !== 'ninguno') {
                    $q->where('sede', $sede);
                }
                // Excluimos usuarios eliminados para que no ensucien la estadística
                $q->where('status', '!=', 'eliminado');
            });

        $registros = $query->orderBy('fecha_diario', 'desc')->get();

        // 2. PROCESADO PARA LA TABLA
        $asistenciasProcesadas = $registros->unique(function ($item) {
                return $item->clave_cliente . Carbon::parse($item->fecha_diario)->toDateString();
            })
            ->map(function($a) {
                $usuario = $a->usuario;
                $pago = $usuario->pago;
                $fecha = Carbon::parse($a->fecha_diario);

                $dias = [
                    'Monday'=>'Lunes', 'Tuesday'=>'Martes', 'Wednesday'=>'Miércoles',
                    'Thursday'=>'Jueves', 'Friday'=>'Viernes', 'Saturday'=>'Sábado', 'Sunday'=>'Domingo'
                ];

                return [
                    'id'            => $a->id,
                    'nombres'       => $usuario->nombres,
                    'apellidos'     => $usuario->apellidos,
                    'clave_usuario' => $a->clave_cliente,
                    'status'        => $usuario->status,
                    'telefono'      => $usuario->telefono,
                    'fecha_diario'  => $a->fecha_diario, 
                    'fecha_corte'   => $pago ? $pago->fecha_corte : null,
                    'dia_nombre'    => $dias[$fecha->format('l')] ?? $fecha->format('l')
                ];
            });

        // 3. CÁLCULO DE STATS FILTRADOS POR SEDE
        $stats = [];
        $diasIngles = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $diasEspanol = ['LUN', 'MAR', 'MIE', 'JUE', 'VIE'];
        $inicioSemana = Carbon::now()->startOfWeek(Carbon::MONDAY);

        foreach ($diasIngles as $index => $diaNombre) {
            $fechaDia = $inicioSemana->copy()->addDays($index);
            
            // Contamos asistencias de usuarios que pertenezcan a la sede
            $conteo = Asistencia::whereDate('fecha_diario', $fechaDia->toDateString())
                ->whereHas('usuario', function($q) use ($sede) {
                    if ($sede && $sede !== 'ninguno') {
                        $q->where('sede', $sede);
                    }
                })
                ->count();

            $stats[] = [
                'dia'   => $diasEspanol[$index],
                'valor' => $conteo
            ];
        }

        return response()->json([
            'status' => true,
            'data'   => array_values($asistenciasProcesadas->toArray()),
            'stats'  => $stats
        ]);

    } catch (\Exception $e) {
        return response()->json(['status' => false, 'error' => $e->getMessage()], 500);
    }
}


public function reporteMensual(Request $request)
{
    try {
        $sede = $request->query('sede');
        
        $inicioMes = Carbon::now()->startOfMonth();
        $finMes = Carbon::now()->endOfMonth();

        // Buscamos asistencias filtrando por la sede del USUARIO relacionado
        $asistencias = Asistencia::with('usuario')
            ->whereBetween('fecha_diario', [$inicioMes, $finMes])
            ->whereHas('usuario', function($q) use ($sede) {
                if ($sede && $sede !== 'ninguno') {
                    $q->where('sede', $sede);
                }
                $q->where('status', '!=', 'eliminado');
            })
            ->orderBy('fecha_diario', 'desc')
            ->get()
            ->map(function($a) {
                return [
                    'fecha_diario'   => $a->fecha_diario,
                    'clave_usuario' => $a->clave_cliente,
                    'fecha'          => Carbon::parse($a->fecha_diario)->format('Y-m-d'),
                    'nombre_usuario' => $a->usuario ? ($a->usuario->nombres . ' ' . $a->usuario->apellidos) : 'N/A',
                    'hora'           => Carbon::parse($a->fecha_diario)->format('H:i:s'),
                    'telefono'       => $a->usuario ? $a->usuario->telefono : 'N/A', // Nuevo
                    'status'         => $a->usuario ? $a->usuario->status : 'N/A',   // Nuevo
                    'sede'           => $a->usuario ? $a->usuario->sede : 'N/A',
                    'fecha_corte'    => ($a->usuario && $a->usuario->pago) ? $a->usuario->pago->fecha_corte : null,
                ];
            });

        return response()->json($asistencias);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false, 
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Obtener asistencias de un cliente específico por su clave (CLIXXX)
     */
    public function show($id)
    {
        $data = Asistencia::where('clave_cliente', $id)
            ->orderBy('fecha_diario', 'desc')
            ->get();

        if ($data->isEmpty()) {
            return response()->json(['message' => 'El socio no registra asistencias'], 404);
        }

        return response()->json($data);
    }

    /**
     * Registrar una nueva asistencia (Desde QR o Manual)
     */
public function store(Request $request)
{
    $validated = $request->validate([
        'clave_cliente' => 'required|string|exists:usuarios,clave_usuario',
        'fecha_diario'  => 'nullable|date',
        'porcentaje'    => 'nullable|string|max:8'
    ]);

    // 1. FORZAR LA HORA DE MÉXICO (O tu zona horaria)
    $zonaHoraria = 'America/Mexico_City';
    $ahora = Carbon::now($zonaHoraria);

    // 2. VALIDACIÓN: Usar la fecha de México para comparar
    $existeAsistencia = Asistencia::where('clave_cliente', $validated['clave_cliente'])
        ->whereDate('fecha_diario', $ahora->toDateString()) // Compara solo Año-Mes-Día
        ->exists();

    if ($existeAsistencia) {
        return response()->json([
            'message' => 'El socio ya registró su entrada hoy ' . $ahora->format('d-m-Y')
        ], 422);
    }

    // 3. CREAR REGISTRO CON LA HORA CORRECTA
    $asistencia = Asistencia::create([
        'clave_cliente' => $validated['clave_cliente'],
        'fecha_diario'  => $ahora, // Se guarda con la hora de México
        'porcentaje'    => $validated['porcentaje'] ?? '100%',
        'created_at'    => $ahora,
        'updated_at'    => $ahora,
    ]);

    return response()->json([
        'message' => 'Asistencia registrada correctamente',
        'data' => $asistencia
    ], 201);
}

    /**
     * Actualizar una asistencia por su ID
     */
    public function update(Request $request, $id)
    {
        $asistencia = Asistencia::find($id);
        
        if (!$asistencia) {
            return response()->json(['message' => 'Registro de asistencia no encontrado'], 404);
        }

        $asistencia->update($request->all());

        return response()->json([
            'message' => 'Registro actualizado',
            'data' => $asistencia
        ]);
    }

    /**
     * Eliminar asistencia
     */
    public function destroy($id)
    {
        $asistencia = Asistencia::find($id);
        
        if (!$asistencia) {
            return response()->json(['message' => 'No se encontró el registro para eliminar'], 404);
        }

        $asistencia->delete();
        return response()->json(['message' => 'Registro de asistencia eliminado con éxito']);
    }

    /**
     * Obtener asistencias por Mes y Año para reportes o calendario
     */
    public function getAsistenciasMes($clave, $year, $month)
    {
        $data = Asistencia::where('clave_cliente', $clave)
            ->whereYear('fecha_diario', $year)
            ->whereMonth('fecha_diario', $month)
            ->orderBy('fecha_diario', 'asc')
            ->get();
        
        return response()->json($data);
    }

    /**
     * Buscar asistencias por texto (Para el buscador de la tabla)
     */
    public function buscar($texto)
    {
        $data = Asistencia::with('usuario')
            ->where('clave_cliente', 'LIKE', "%$texto%")
            ->orWhereHas('usuario', function($query) use ($texto) {
                $query->where('nombres', 'LIKE', "%$texto%")
                      ->orWhere('apellidos', 'LIKE', "%$texto%");
            })
            ->orderBy('fecha_diario', 'desc')
            ->get();

        return response()->json($data);
    }

public function verificarRostro(Request $request) {
    try {
        $user = Usuario::where('clave_usuario', $request->clave)->first();
        if (!$user || !$user->ruta_imagen) {
            return response()->json(['success' => false, 'mensaje' => 'Usuario sin foto'], 404);
        }

        // 1. Preparar las imágenes (La guardada y la que viene de la cámara)
        $fotoGuardada = public_path('storage/' . $user->ruta_imagen);
        $imgData = str_replace('data:image/jpeg;base64,', '', $request->image);
        $fotoCaptura = base64_decode(str_replace(' ', '+', $imgData));

        // 2. Llamada a la API de Internet (Ejemplo: Azure Face API)
        // Nota: Necesitas una KEY y un ENDPOINT que te dan al registrarte (gratis)
        $apiKey = 'TU_AZURE_KEY';
        $endpoint = 'https://tu-recurso.cognitiveservices.azure.com/face/v1.0';

        // Paso A: Detectar rostro en foto de la cámara y obtener un ID
        $res1 = Http::withHeaders(['Ocp-Apim-Subscription-Key' => $apiKey])
            ->withBody($fotoCaptura, 'application/octet-stream')
            ->post("$endpoint/detect?returnFaceId=true");

        // Paso B: Detectar rostro en la foto guardada
        $res2 = Http::withHeaders(['Ocp-Apim-Subscription-Key' => $apiKey])
            ->withBody(File::get($fotoGuardada), 'application/octet-stream')
            ->post("$endpoint/detect?returnFaceId=true");

        $faceId1 = $res1->json()[0]['faceId'] ?? null;
        $faceId2 = $res2->json()[0]['faceId'] ?? null;

        if (!$faceId1 || !$faceId2) {
            return response()->json(['success' => false, 'mensaje' => 'No se detectó rostro'], 400);
        }

        // Paso C: Verificar si son la misma persona
        $verificacion = Http::withHeaders(['Ocp-Apim-Subscription-Key' => $apiKey])
            ->post("$endpoint/verify", [
                'faceId1' => $faceId1,
                'faceId2' => $faceId2
            ]);

        // 3. Respuesta final
        if ($verificacion->json()['isIdentical'] ?? false) {
            // REGISTRAR ASISTENCIA AQUÍ (Igual que antes)
            Asistencia::create([
                'clave_cliente' => $user->clave_usuario,
                'fecha_diario'  => now(),
                'porcentaje'    => '100%'
            ]);

            return response()->json(['success' => true, 'mensaje' => 'Bienvenido ' . $user->nombres]);
        }

        return response()->json(['success' => false, 'mensaje' => 'El rostro no coincide'], 401);

    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => 'Error de conexión con la IA'], 500);
    }
}    
}