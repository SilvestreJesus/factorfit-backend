<?php

namespace App\Http\Controllers;

use App\Models\Personal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// Importamos Cloudinary para el manejo de imágenes en la nube
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class PersonalController extends Controller
{
    public function index(Request $request)
    {
        $query = Personal::query();

        // Filtrar por sede si se pasa como query
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

        // SUBIDA A CLOUDINARY
        if ($request->hasFile('ruta_imagen')) {
            // Subimos a la carpeta 'personal' en Cloudinary
            $result = Cloudinary::upload($request->file('ruta_imagen')->getRealPath(), [
                'folder' => 'personal'
            ]);
            // Guardamos la URL segura que nos devuelve Cloudinary
            $validated['ruta_imagen'] = $result->getSecurePath();
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
            'message' => 'Personal registrado correctamente en Cloudinary',
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
        $personal->rol = $request->rol ?? 'personal';

        if ($request->hasFile('ruta_imagen')) {
            // 1. BORRAR FOTO ANTERIOR DE CLOUDINARY PARA NO ACUMULAR BASURA
            if ($personal->ruta_imagen) {
                $publicId = $this->getPublicIdFromUrl($personal->ruta_imagen);
                if ($publicId) {
                    Cloudinary::destroy($publicId);
                }
            }

            // 2. SUBIR LA NUEVA
            $result = Cloudinary::upload($request->file('ruta_imagen')->getRealPath(), [
                'folder' => 'personal'
            ]);
            $personal->ruta_imagen = $result->getSecurePath();
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

        // ELIMINAR FOTO DE CLOUDINARY
        if ($item->ruta_imagen) {
            $publicId = $this->getPublicIdFromUrl($item->ruta_imagen);
            if ($publicId) {
                Cloudinary::destroy($publicId);
            }
        }

        $item->delete();
        return response()->json(['message' => 'Personal e imagen eliminados correctamente']);
    }

    // FUNCIÓN PARA EXTRAER EL ID (Buscando la carpeta 'personal')
    private function getPublicIdFromUrl($url)
    {
        $path = parse_url($url, PHP_URL_PATH);
        $parts = explode('/', $path);
        
        $index = array_search('personal', $parts);
        
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
        $result = Personal::where('nombre_completo', 'LIKE', "%$texto%")
            ->orWhere('puesto', 'LIKE', "%$texto%")
            ->get();

        return response()->json($result);
    }
}