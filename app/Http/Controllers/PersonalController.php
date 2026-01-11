<?php

namespace App\Http\Controllers;

use App\Models\Personal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PersonalController extends Controller
{
    public function index(Request $request)
    {
        $query = Personal::query();

        // Filtrar por sede si se pasa como query (opcional)
        if ($request->has('sede')) {
            $sede = $request->query('sede');
            $query->where('sede', $sede);
        }

        return response()->json($query->get());
    }

    public function show($id)
    {
        $item = Personal::find($id);
        return $item ? response()->json($item)
                     : response()->json(['message' => 'Registro no encontrado'], 404);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_completo' => 'required|string|max:150',
            'puesto' => 'required|string|max:50',
            'descripcion' => 'nullable|string',
            'sede' => 'required|string|max:30',
            'rol' => 'nullable|string|max:30',
            'ruta_imagen' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048'
        ]);


        $validated['rol'] = $validated['rol'] ?? 'personal';

        // Subir imagen si viene
        if ($request->hasFile('ruta_imagen')) {
            $path = $request->file('ruta_imagen')->store('personal', 'public');
            $validated['ruta_imagen'] = "storage/$path";
        }


        $personal = null;

        DB::transaction(function () use (&$personal, $validated) {

            $lastNumber = DB::table('personal')
                ->selectRaw("MAX(CAST(SUBSTRING(clave_personal FROM 5) AS INTEGER)) AS max_num")
                ->value('max_num');

            $newNumber = $lastNumber ? $lastNumber + 1 : 1;

            $validated['clave_personal'] = 'PERS' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);

            $personal = Personal::create($validated);
        });

        return response()->json([
            'message' => 'Personal registrado correctamente',
            'personal' => $personal
        ], 201);
    }



    public function update(Request $request, $clave)
    {
        $personal = Personal::where('clave_personal', $clave)->firstOrFail();

        $personal->nombre_completo = $request->nombre_completo;
        $personal->puesto = $request->puesto;
        $personal->descripcion = $request->descripcion;
        $personal->sede = $request->sede;
        $personal->rol = 'personal';

        if ($request->hasFile('ruta_imagen')) {

        // Eliminar imagen anterior si existe
        if ($personal->ruta_imagen) {
            // Convierte "storage/personal/archivo.jpg" a "storage/app/public/personal/archivo.jpg"
            $rutaAnterior = str_replace('storage', storage_path('app/public'), $personal->ruta_imagen);

            if (file_exists($rutaAnterior)) {
                unlink($rutaAnterior);
            }
        }

        // Subir nueva imagen
        $rutaNueva = $request->file('ruta_imagen')->store('personal', 'public');
        $personal->ruta_imagen = 'storage/' . $rutaNueva;
        }
        $personal->save();

        return response()->json(['message' => 'Personal actualizado correctamente']);
    }


    public function destroy($clave_personal)
    {
        $item = Personal::where('clave_personal', $clave_personal)->first();

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
        $result = Personal::where('nombre_completo', 'LIKE', "%$texto%")
            ->orWhere('puesto', 'LIKE', "%$texto%")
            ->get();

        return response()->json($result);
    }
}
