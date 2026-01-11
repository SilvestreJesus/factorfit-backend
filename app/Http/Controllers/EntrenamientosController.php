<?php

namespace App\Http\Controllers;

use App\Models\Entrenamientos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EntrenamientosController extends Controller
{
    public function index(Request $request)
    {
        $query = Entrenamientos::query();

        // Filtrar por sede si se pasa como query (opcional)
        if ($request->has('sede')) {
            $sede = $request->query('sede');
            $query->where('sede', $sede);
        }

        return response()->json($query->get());
    }

    public function show($id)
    {
        $item = Entrenamientos::find($id);
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
            $path = $request->file('ruta_imagen')->store('entrenamientos', 'public');
            $validated['ruta_imagen'] = "storage/$path";
        }


        $entrenamientos = null;

        DB::transaction(function () use (&$entrenamientos, $validated) {

            $lastNumber = DB::table('entrenamientos')
                ->selectRaw("MAX(CAST(SUBSTRING(clave_entrenamientos FROM 5) AS INTEGER)) AS max_num")
                ->value('max_num');

            $newNumber = $lastNumber ? $lastNumber + 1 : 1;

            $validated['clave_entrenamientos'] = 'ETRE' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);

            $entrenamientos = Entrenamientos::create($validated);
        });

        return response()->json([
            'message' => 'entrenamientos registrado correctamente',
            'entrenamientos' => $entrenamientos
        ], 201);
    }



    public function update(Request $request, $clave)
    {
        $entrenamientos = Entrenamientos::where('clave_entrenamientos', $clave)->firstOrFail();

        $entrenamientos->titulo = $request->titulo;
        $entrenamientos->descripcion = $request->descripcion;
        $entrenamientos->sede = $request->sede;

        if ($request->hasFile('ruta_imagen')) {

            // Eliminar imagen anterior si existe
            if ($entrenamientos->ruta_imagen) {
                $rutaAnterior = str_replace('storage', storage_path('app/public'), $entrenamientos->ruta_imagen);
                if (file_exists($rutaAnterior)) {
                    unlink($rutaAnterior);
                }
            }

            // Subir nueva imagen
            $rutaNueva = $request->file('ruta_imagen')->store('entrenamientos', 'public');
            $entrenamientos->ruta_imagen = 'storage/' . $rutaNueva;
        }


        $entrenamientos->save();

        return response()->json(['message' => 'entrenamientos actualizado correctamente']);
    }


    public function destroy($clave_entrenamientos)
    {
        $item = Entrenamientos::where('clave_entrenamientos', $clave_entrenamientos)->first();

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
        $result = Entrenamientos::where('titulo', 'LIKE', "%$texto%")
            ->get();

        return response()->json($result);
    }
}
