<?php
namespace App\Http\Controllers;

use App\Models\Pago;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Usuario;


class PagoController extends Controller
{
   public function index(Request $request)
    {
        $query = Pago::with('usuario')->orderBy('updated_at', 'desc');

        // Filtrar por tipo de pago
        if ($request->has('tipo') && strtolower($request->tipo) !== 'todos') {
            $query->where('Tipo_pago', $request->tipo); // "Mensual" o "Quincenal"
        }

        // Filtrar por sede
        if ($request->has('sede') && $request->sede !== '') {
            $query->whereHas('usuario', function($q) use ($request){
                $q->where('sede', $request->sede);
            });
        }
        
        return response()->json($query->get());
    }

    public function show($id)
    {
        $pago = Pago::with('usuario')->where('clave_cliente', $id)->first();
        return $pago ? response()->json($pago)
                     : response()->json(['message' => 'Pago no encontrado'], 404);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'clave_cliente' => 'required|string|exists:usuarios,clave_usuario',
            'fecha_ingreso' => 'nullable|date',
            'fecha_corte' => 'nullable|date',
            'Tipo_pago' => 'nullable|string|max:20',
            'monto_pagado' => 'nullable|numeric',
            'monto_pendiente' => 'nullable|numeric',
            'monto_recargo' => 'nullable|numeric',
        ]);

        $pago = Pago::create($validated);

        return response()->json(['message' => 'Pago registrado', 'pago' => $pago], 201);
    }

    public function update(Request $request, $id) {
    $pago = Pago::where('clave_cliente', $id)->first();
    
    if (!$pago) return response()->json(['message' => 'No encontrado'], 404);

    // USAMOS LOS NOMBRES QUE VIENEN DE ANGULAR
    $pago->monto_pendiente = $request->monto_pendiente; 
    $pago->monto_pagado    = $request->monto_pagado;    // Antes decía monto_abonado
    $pago->fecha_corte     = $request->fecha_corte;
    $pago->monto_recargo   = $request->monto_recargo;

    // NO tocamos $pago->Tipo_pago para que se mantenga el original (Mensual/Quincenal)

    if ($request->monto_pendiente <= 0) {
        $usuario = Usuario::where('clave_usuario', $id)->first();
        if ($usuario) {
            $usuario->status = 'activo';
            $usuario->save();
        }
    }
    
    $pago->save();
    return response()->json(['message' => 'Pago procesado con éxito']);
}

    public function destroy($id)
    {
        Pago::where('clave_cliente', $id)->delete();
        return response()->json(['message' => 'Registro(s) de pago eliminado(s)']);
    }



private function procesarEstadoYRecargo($pago, $hoy)
{
    $usuario = $pago->usuario;
    if (!$usuario || !$pago->fecha_corte) return;

    $fechaCorte = Carbon::parse($pago->fecha_corte);
    $costoMensual = 500;

    // 1. CALCULAR MESES TRANSCURRIDOS (Enteros)
    $mesesVencidos = (int) $fechaCorte->diffInMonths($hoy);

    // 2. REGLA DE LOS 3 DÍAS (ANTICIPO)
    $proximoCorte = $fechaCorte->copy()->addMonths($mesesVencidos + 1);
    $diasParaSiguiente = $hoy->diffInDays($proximoCorte, false);

    if ($diasParaSiguiente <= 3) {
        $mesesVencidos++;
    }

    // 3. CALCULAR MONTO PENDIENTE
    if ($hoy->greaterThanOrEqualTo($fechaCorte->copy()->subDays(3))) {
        $cantidadMeses = ($mesesVencidos > 0) ? $mesesVencidos : 1;
        $pago->monto_pendiente = $cantidadMeses * $costoMensual;
    } else {
        $pago->monto_pendiente = 0;
    }

    // 4. RECARGOS (¡AQUÍ ESTÁ EL CAMBIO!)
    // Condición: Solo se cobra recargo si el usuario NO es nuevo 
    // (es decir, ya ha tenido un monto_pagado histórico > 0)
    $esNuevo = (is_null($pago->monto_pagado) || $pago->monto_pagado <= 0);

    if (!$esNuevo && $hoy->greaterThan($fechaCorte->copy()->addDays(3))) {
        $semanasRetraso = (int) ceil($fechaCorte->diffInDays($hoy) / 7);
        $pago->monto_recargo = $semanasRetraso * 100;
    } else {
        // Si es nuevo o está a tiempo, el recargo es 0
        $pago->monto_recargo = 0;
    }

    // 5. LÓGICA DE STATUS
    if ($pago->monto_pendiente <= 0) {
        $usuario->status = 'activo';
    } else {
        $diferenciaDias = $fechaCorte->diffInDays($hoy, false);

        if ($mesesVencidos >= 2) {
            $usuario->status = 'inactivo';
        } elseif ($diferenciaDias < 0 && abs($diferenciaDias) <= 3) {
            $usuario->status = 'proximo a vencer';
        } else {
            $usuario->status = 'pendiente';
        }
    }

    $pago->save();
    $usuario->save();
}
    


public function actualizarEstadosYRecargos()
{
    $hoy = Carbon::now();

    // Filtramos DESDE el query de SQL para no saturar la memoria con eliminados
    Pago::whereHas('usuario', function($q) {
        $q->where('status', '!=', 'eliminado');
    })
    ->with('usuario')
    ->chunk(100, function($pagos) use ($hoy) {
        foreach ($pagos as $pago) {
            $this->procesarEstadoYRecargo($pago, $hoy);
        }
    });

    return true;
}


public function actualizarPagos(Request $request)
{
    $sede = $request->query('sede');
    $hoy = Carbon::now();

    // FILTRO CLAVE: Solo traer pagos de usuarios que NO están eliminados
    $query = Pago::whereHas('usuario', function($q) {
        $q->where('status', '!=', 'eliminado');
    });

    // Si filtran por sede, se añade a la condición del usuario
    if ($sede) {
        $query->whereHas('usuario', function($q) use ($sede) {
            $q->where('sede', $sede)
              ->where('status', '!=', 'eliminado');
        });
    }

    // Cargamos la relación 'usuario' solo para los que pasaron el filtro anterior
    $pagos = $query->with('usuario')->get();

    foreach ($pagos as $pago) {
        $this->procesarEstadoYRecargo($pago, $hoy);
    }

    return response()->json([
        'message' => 'Sincronización de sede ' . ($sede ?? 'Todas') . ' completada'
    ]);
}

// Agrega esta función a tu PagoController.php
public function bitacoraRecuperacion(Request $request) 
{
    try {
        $sede = trim($request->query('sede'));

        $query = Pago::with('usuario')
            ->whereHas('usuario', function($q) use ($sede) {
                if ($sede && $sede !== '' && $sede !== 'null') {
                    $q->where('sede', $sede);
                }
                // AQUÍ QUITAMOS EL FILTRO DE ELIMINADOS
                $q->where('status', 'eliminado'); 
            });

        $pagos = $query->get()->map(function($pago) {
            return [
                'clave'       => $pago->clave_cliente,
                'fecha_corte' => $pago->fecha_corte,
                'monto_pendiente' => $pago->monto_pendiente,
                'monto_recargo'   => $pago->monto_recargo
            ];
        });

        return response()->json(['status' => true, 'data' => $pagos]);
    } catch (\Exception $e) {
        return response()->json(['status' => false, 'error' => $e->getMessage()], 500);
    }
}


public function bitacora(Request $request) 
{
    try {
        // Obtenemos la sede y limpiamos espacios
        $sede = trim($request->query('sede'));

        $query = Pago::with('usuario')
            ->whereHas('usuario', function($q) use ($sede) {
                // Filtro estricto de sede si se proporciona
                if ($sede && $sede !== '' && $sede !== 'null' && $sede !== 'undefined') {
                    $q->where('sede', $sede);
                }
                // Seguridad: No mostrar eliminados
                $q->where('status', '!=', 'eliminado');
            });

        $pagos = $query->orderBy('updated_at', 'desc')->get()->map(function($pago) {
            return [
                'clave'           => $pago->clave_cliente,
                'nombre'          => ($pago->usuario->nombres ?? 'Sin nombre') . ' ' . ($pago->usuario->apellidos ?? ''),
                'tipo_pago'       => $pago->Tipo_pago,
                'monto'           => $pago->monto_pagado,
                'status'          => $pago->usuario->status ?? 'inactivo',
                'updated_at'      => $pago->updated_at,
                'monto_pendiente' => $pago->monto_pendiente, 
                'monto_recargo'   => $pago->monto_recargo,
                'telefono'        => $pago->usuario->telefono ?? '',
                'fecha_corte'     => $pago->fecha_corte,
                // Aseguramos que la sede se devuelva para el filtro del front
                'sede'            => $pago->usuario->sede ?? 'N/A' 
            ];
        });

        return response()->json([
            'status' => true,
            'data'   => $pagos
        ]);

    } catch (\Exception $e) {
        return response()->json(['status' => false, 'error' => $e->getMessage()], 500);
    }
}




}