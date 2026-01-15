<?php

namespace App\Http\Controllers;

use App\Models\Personal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; 
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class PersonalController extends Controller
{
    public function index(Request $request)
    {
        $query = Personal::latest();

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
            'ruta_imagen' => 'nullable|string' 
            
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
        try {
            $personal = Personal::where('clave_personal', $clave)->firstOrFail();
            $urlVieja = $personal->ruta_imagen;
            $urlNueva = $request->input('ruta_imagen');

            // Solo intentamos borrar si la URL cambió y no está vacía
            if (!empty($urlNueva) && $urlNueva !== $urlVieja) {
                $this->borrarImagenCloudinary($urlVieja);
            }

            $personal->update($request->only(['nombre_completo', 'puesto', 'descripcion', 'sede', 'ruta_imagen']));
            return response()->json(['message' => 'Actualizado correctamente']);
        } catch (\Exception $e) {
            Log::error("Error en Update personal: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($clave)
    {
        try {
            $personal = Personal::where('clave_personal', $clave)->firstOrFail();
            
            if ($personal->ruta_imagen) {
                $this->borrarImagenCloudinary($personal->ruta_imagen);
            }
            
            $personal->delete();
            return response()->json(['message' => 'Eliminado correctamente']);
        } catch (\Exception $e) {
            Log::error("Error en Destroy personal: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destruirImagen(Request $request)
    {
        $url = $request->input('url');
        if ($url && str_contains($url, 'cloudinary.com')) {
            try {
                $publicId = $this->getPublicIdFromUrl($url);
                if ($publicId) {
                    Cloudinary::destroy($publicId);
                    return response()->json(['message' => 'Imagen eliminada de la nube']);
                }
            } catch (\Exception $e) {
                Log::warning("No se pudo borrar la imagen en Cloudinary: " . $e->getMessage());
            }
        }
        return response()->json(['message' => 'Proceso completado (con o sin borrado de nube)']);
    }

    private function borrarImagenCloudinary($url)
    {
        try {
            if ($url && str_contains($url, 'cloudinary.com')) {
                $publicId = $this->getPublicIdFromUrl($url);
                if ($publicId) {
                    Cloudinary::destroy($publicId);
                }
            }
        } catch (\Exception $e) {
            Log::error("Falla crítica al borrar en Cloudinary: " . $e->getMessage());
        }
    }

    private function getPublicIdFromUrl($url)
    {
        // Esta regex extrae "personal/nombre_archivo" de la URL de Cloudinary
        if (preg_match('/upload\/v\d+\/(.+)\.[a-z]{3,4}$/i', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }



}