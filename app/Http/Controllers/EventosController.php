<?php

namespace App\Http\Controllers;

use App\Models\Eventos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// Importamos el Facade de Cloudinary
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class EventosController extends Controller
{
    public function index(Request $request)
    {
        $query = Eventos::query();

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

        // SUBIDA A CLOUDINARY
        if ($request->hasFile('ruta_imagen')) {
            // Se sube a la carpeta 'eventos' dentro de Cloudinary
            $result = Cloudinary::upload($request->file('ruta_imagen')->getRealPath(), [
                'folder' => 'eventos'
            ]);
            // Guardamos la URL segura que nos da la nube
            $validated['ruta_imagen'] = $result->getSecurePath();
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
            'message' => 'Evento registrado correctamente en Cloudinary',
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
            // 1. ELIMINAR IMAGEN ANTERIOR DE LA NUBE
            if ($eventos->ruta_imagen) {
                $publicId = $this->getPublicIdFromUrl($eventos->ruta_imagen);
                if ($publicId) {
                    Cloudinary::destroy($publicId);
                }
            }

            // 2. SUBIR LA NUEVA IMAGEN
            $result = Cloudinary::upload($request->file('ruta_imagen')->getRealPath(), [
                'folder' => 'eventos'
            ]);
            $eventos->ruta_imagen = $result->getSecurePath();
        }

        $eventos->save();

        return response()->json(['message' => 'Evento actualizado correctamente']);
    }

    public function destroy($clave_eventos)
    {
        $item = Eventos::where('clave_eventos', $clave_eventos)->first();

        if (!$item) {
            return response()->json(['message' => 'Registro no encontrado'], 404);
        }

        // ELIMINAR IMAGEN DE CLOUDINARY
        if ($item->ruta_imagen) {
            $publicId = $this->getPublicIdFromUrl($item->ruta_imagen);
            if ($publicId) {
                Cloudinary::destroy($publicId);
            }
        }

        $item->delete();

        return response()->json(['message' => 'Evento e imagen eliminados correctamente']);
    }

    // FUNCIÓN AUXILIAR PARA ELIMINAR DE CLOUDINARY
    private function getPublicIdFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $parts = explode('/', $path);
        
        $index = array_search('eventos', $parts);
        
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
        $result = Eventos::where('titulo', 'LIKE', "%$texto%")->get();

        return response()->json($result);
    }    
}