<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Usuario;
use Illuminate\Http\Request;
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

        // Si no se envía fecha, usamos la hora actual del servidor
        $fechaAsistencia = $request->fecha_diario ? Carbon::parse($request->fecha_diario) : Carbon::now();

        // VALIDACIÓN: Evitar que el usuario marque dos veces el mismo día
        $existeAsistencia = Asistencia::where('clave_cliente', $validated['clave_cliente'])
            ->whereDate('fecha_diario', $fechaAsistencia->toDateString())
            ->exists();

        if ($existeAsistencia) {
            return response()->json([
                'message' => 'El socio ya registró su entrada el día de hoy'
            ], 422); // Código 422: Entidad no procesable
        }

        // Crear registro
        $asistencia = Asistencia::create([
            'clave_cliente' => $validated['clave_cliente'],
            'fecha_diario'  => $fechaAsistencia,
            'porcentaje'    => $validated['porcentaje'] ?? '100%',
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
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
}