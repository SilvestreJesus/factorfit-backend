<?php

namespace App\Http\Controllers;

use App\Models\Instalaciones;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; 
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
class InstalacionesController extends Controller
{
    public function index(Request $request)
    {
        $query = Instalaciones::latest();
        if ($request->has('sede')) {
            $query->where('sede', $request->query('sede'));
        }
        return response()->json($query->get());
    }

    public function show($id)
    {
        // Usamos find para ser consistentes con Instalaciones
        $item = Instalaciones::find($id);
        return $item ? response()->json($item) 
                     : response()->json(['message' => 'No encontrado'], 404);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'titulo'      => 'required|string|max:150',
                'descripcion' => 'nullable|string',
                'sede'        => 'required|string|max:30',
                'ruta_imagen' => 'nullable|string' 
            ]);

            $instalacion = null;
            DB::transaction(function () use (&$instalacion, $validated) {
                // Usamos INTEGER para que sea igual a Instalaciones
                $lastNumber = DB::table('instalaciones')
                    ->selectRaw("MAX(CAST(SUBSTRING(clave_instalaciones FROM 5) AS INTEGER)) AS max_num")
                    ->value('max_num');

                $newNumber = $lastNumber ? $lastNumber + 1 : 1;
                $validated['clave_instalaciones'] = 'INST' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);

                $instalacion = Instalaciones::create($validated);
            });

            return response()->json($instalacion, 201);
        } catch (\Exception $e) {
            Log::error("Error en Store Instalaciones: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

public function update(Request $request, $clave)
    {
        try {
            $item = Instalaciones::where('clave_instalaciones', $clave)->firstOrFail();
            $urlVieja = $item->ruta_imagen;
            $urlNueva = $request->input('ruta_imagen');

            // Solo intentamos borrar si la URL cambió y no está vacía
            if (!empty($urlNueva) && $urlNueva !== $urlVieja) {
                $this->borrarImagenCloudinary($urlVieja);
            }

            $item->update($request->only(['titulo', 'descripcion', 'sede', 'ruta_imagen']));
            return response()->json(['message' => 'Actualizado correctamente']);
        } catch (\Exception $e) {
            Log::error("Error en Update Instalaciones: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($clave)
    {
        try {
            $item = Instalaciones::where('clave_instalaciones', $clave)->firstOrFail();
            
            if ($item->ruta_imagen) {
                $this->borrarImagenCloudinary($item->ruta_imagen);
            }
            
            $item->delete();
            return response()->json(['message' => 'Eliminado correctamente']);
        } catch (\Exception $e) {
            Log::error("Error en Destroy instalaciones: " . $e->getMessage());
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
        // Esta regex extrae "Instalaciones/nombre_archivo" de la URL de Cloudinary
        if (preg_match('/upload\/v\d+\/(.+)\.[a-z]{3,4}$/i', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }


}