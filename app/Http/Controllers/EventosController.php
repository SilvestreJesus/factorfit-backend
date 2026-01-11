<?php

namespace App\Http\Controllers;

use App\Models\Eventos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventosController extends Controller
{
    public function index(Request $request)
    {
        $query = Eventos::query();

        // Filtrar por sede si se pasa como query (opcional)
        if ($request->has('sede')) {
            $sede = $request->query('sede');
            $query->where('sede', $sede);
        }

        return response()->json($query->get());
    }

    public function show($id)
    {
        $item = Eventos::find($id);
        return $item ? response()->json($item)
                     : response()->json(['message' => 'Registro no encontrado'], 404);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:150',
            'descripcion' => 'nullable|string',
            'sede' => 'required|string|max:30',
            'ruta_imagen' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048'
        ]);



        // Subir imagen si viene
        if ($request->hasFile('ruta_imagen')) {
            $path = $request->file('ruta_imagen')->store('eventos', 'public');
            $validated['ruta_imagen'] = "storage/$path";
        }


        $eventos = null;

        DB::transaction(function () use (&$eventos, $validated) {

            $lastNumber = DB::table('eventos')
                ->selectRaw("MAX(CAST(SUBSTRING(clave_eventos FROM 5) AS INTEGER)) AS max_num")
                ->value('max_num');

            $newNumber = $lastNumber ? $lastNumber + 1 : 1;

            $validated['clave_eventos'] = 'EVEN' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);

            $eventos = Eventos::create($validated);
        });

        return response()->json([
            'message' => 'eventos registrado correctamente',
            'eventos' => $eventos
        ], 201);
    }



    public function update(Request $request, $clave)
    {
        $eventos = Eventos::where('clave_eventos', $clave)->firstOrFail();


        $eventos->titulo = $request->titulo;
        $eventos->descripcion = $request->descripcion;
        $eventos->sede = $request->sede;

        if ($request->hasFile('ruta_imagen')) {

            // Eliminar imagen anterior si existe
            if ($eventos->ruta_imagen) {
                // Convierte "storage/eventos/archivo.jpg" a "storage/app/public/eventos/archivo.jpg"
                $rutaAnterior = str_replace('storage', storage_path('app/public'), $eventos->ruta_imagen);

                if (file_exists($rutaAnterior)) {
                    unlink($rutaAnterior);
                }
            }

            // Subir nueva imagen
            $rutaNueva = $request->file('ruta_imagen')->store('eventos', 'public');
            $eventos->ruta_imagen = 'storage/' . $rutaNueva;
        }


        $eventos->save();

        return response()->json(['message' => 'eventos actualizado correctamente']);
    }


    public function destroy($clave_eventos)
    {
        $item = Eventos::where('clave_eventos', $clave_eventos)->first();

        if (!$item) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }

        // Eliminar imagen física si existe
        if ($item->ruta_imagen && file_exists(public_path($item->ruta_imagen))) {
            unlink(public_path($item->ruta_imagen));
        }

        // Eliminar registro
        $item->delete();

        return response()->json(['message' => 'Eliminado correctamente']);
    }
    // Opcional: búsqueda por texto
    public function buscar(Request $request)
    {
        $texto = $request->input('texto', '');
        $result = Eventos::where('titulo', 'LIKE', "%$texto%")
            ->get();

        return response()->json($result);
    }
}
