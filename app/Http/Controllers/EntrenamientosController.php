<?php

namespace App\Http\Controllers;

use App\Models\Entrenamientos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// Importamos el Facade de Cloudinary
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class EntrenamientosController extends Controller
{
    public function index(Request $request)
    {
        $query = Entrenamientos::query();

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

        if ($request->hasFile('ruta_imagen')) {
            $result = Cloudinary::upload($request->file('ruta_imagen')->getRealPath(), [
                'folder' => 'entrenamientos'
            ]);
            $validated['ruta_imagen'] = $result->getSecurePath();
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
            'message' => 'Entrenamiento registrado correctamente',
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
            // 1. ELIMINAR IMAGEN ANTERIOR DE CLOUDINARY
            if ($entrenamientos->ruta_imagen) {
                $publicId = $this->getPublicIdFromUrl($entrenamientos->ruta_imagen);
                if ($publicId) {
                    Cloudinary::destroy($publicId);
                }
            }

            // 2. SUBIR LA NUEVA
            $result = Cloudinary::upload($request->file('ruta_imagen')->getRealPath(), [
                'folder' => 'entrenamientos'
            ]);
            $entrenamientos->ruta_imagen = $result->getSecurePath();
        }

        $entrenamientos->save();

        return response()->json(['message' => 'Entrenamiento actualizado correctamente']);
    }

    public function destroy($clave_entrenamientos)
    {
        $item = Entrenamientos::where('clave_entrenamientos', $clave_entrenamientos)->first();

        if (!$item) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }

        // ELIMINAR IMAGEN DE CLOUDINARY AL BORRAR EL REGISTRO
        if ($item->ruta_imagen) {
            $publicId = $this->getPublicIdFromUrl($item->ruta_imagen);
            if ($publicId) {
                Cloudinary::destroy($publicId);
            }
        }

        $item->delete();

        return response()->json(['message' => 'Entrenamiento e imagen eliminados correctamente']);
    }

    // FUNCIÓN AUXILIAR PARA EXTRAER EL PUBLIC ID
    private function getPublicIdFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $parts = explode('/', $path);
        
        $index = array_search('entrenamientos', $parts);
        
        if ($index !== false) {
            $relevantParts = array_slice($parts, $index);
            $fileWithExtension = end($relevantParts);
            $fileName = pathinfo($fileWithExtension, PATHINFO_FILENAME);
            
            array_pop($relevantParts);
            $relevantParts[] = $fileName;
            
            return implode('/', $relevantParts);
        }
        return null;
    }

    public function buscar(Request $request)
    {
        $texto = $request->input('texto', '');
        $result = Entrenamientos::where('titulo', 'LIKE', "%$texto%")->get();

        return response()->json($result);
    }
}