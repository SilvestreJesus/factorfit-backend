<?php

namespace App\Http\Controllers;

use App\Models\Instalaciones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// Importamos Cloudinary
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class InstalacionesController extends Controller
{
    public function index(Request $request)
    {
        $query = Instalaciones::query();

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

        // SUBIDA A CLOUDINARY
        if ($request->hasFile('ruta_imagen')) {
            $result = Cloudinary::upload($request->file('ruta_imagen')->getRealPath(), [
                'folder' => 'instalaciones'
            ]);
            // Guardamos la URL permanente de la nube
            $validated['ruta_imagen'] = $result->getSecurePath();
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
            'message' => 'Instalación registrada correctamente en Cloudinary',
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
            // 1. ELIMINAR IMAGEN ANTERIOR DE CLOUDINARY
            if ($instalaciones->ruta_imagen) {
                $publicId = $this->getPublicIdFromUrl($instalaciones->ruta_imagen);
                if ($publicId) {
                    Cloudinary::destroy($publicId);
                }
            }

            // 2. SUBIR LA NUEVA
            $result = Cloudinary::upload($request->file('ruta_imagen')->getRealPath(), [
                'folder' => 'instalaciones'
            ]);
            $instalaciones->ruta_imagen = $result->getSecurePath();
        }

        $instalaciones->save();

        return response()->json(['message' => 'Instalación actualizada correctamente']);
    }

    public function destroy($clave_instalaciones)
    {
        $item = Instalaciones::where('clave_instalaciones', $clave_instalaciones)->first();

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

        return response()->json(['message' => 'Instalación e imagen eliminadas correctamente']);
    }

    // FUNCIÓN AUXILIAR PARA EXTRAER EL PUBLIC ID DE CLOUDINARY
    private function getPublicIdFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $parts = explode('/', $path);
        
        // Buscamos la carpeta contenedora 'instalaciones'
        $index = array_search('instalaciones', $parts);
        
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
        $result = Instalaciones::where('titulo', 'LIKE', "%$texto%")->get();

        return response()->json($result);
    }
}