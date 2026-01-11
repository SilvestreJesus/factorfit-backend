<?php

namespace App\Http\Controllers;

use App\Models\Instalaciones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstalacionesController extends Controller
{
    public function index(Request $request)
    {
        $query =Instalaciones::query();

        // Filtrar por sede si se pasa como query (opcional)
        if ($request->has('sede')) {
            $sede = $request->query('sede');
            $query->where('sede', $sede);
        }

        return response()->json($query->get());
    }

    public function show($id)
    {
        $item = Instalaciones::find($id);
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
            $path = $request->file('ruta_imagen')->store('instalaciones', 'public');
            $validated['ruta_imagen'] = "storage/$path";
        }


        $instalaciones = null;

        DB::transaction(function () use (&$instalaciones, $validated) {

            $lastNumber = DB::table('instalaciones')
                ->selectRaw("MAX(CAST(SUBSTRING(clave_instalaciones FROM 5) AS INTEGER)) AS max_num")
                ->value('max_num');

            $newNumber = $lastNumber ? $lastNumber + 1 : 1;

            $validated['clave_instalaciones'] = 'INST' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);

            $instalaciones = Instalaciones::create($validated);
        });

        return response()->json([
            'message' => 'instalaciones registrado correctamente',
            'instalaciones' => $instalaciones
        ], 201);
    }



    public function update(Request $request, $clave)
    {
        $instalaciones = Instalaciones::where('clave_instalaciones', $clave)->firstOrFail();

        $instalaciones->titulo = $request->titulo;
        $instalaciones->descripcion = $request->descripcion;
        $instalaciones->sede = $request->sede;

        if ($request->hasFile('ruta_imagen')) {

            // Eliminar imagen anterior si existe
            if ($instalaciones->ruta_imagen) {
                $rutaAnterior = str_replace('storage', storage_path('app/public'), $instalaciones->ruta_imagen);
                if (file_exists($rutaAnterior)) {
                    unlink($rutaAnterior);
                }
            }

            // Subir nueva imagen
            $rutaNueva = $request->file('ruta_imagen')->store('instalaciones', 'public');
            $instalaciones->ruta_imagen = 'storage/' . $rutaNueva;
        }


        $instalaciones->save();

        return response()->json(['message' => 'instalaciones actualizado correctamente']);
    }


    public function destroy($clave_instalaciones)
    {
        $item = Instalaciones::where('clave_instalaciones', $clave_instalaciones)->first();

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
        $result = Instalaciones::where('titulo', 'LIKE', "%$texto%")
            ->get();

        return response()->json($result);
    }
}
