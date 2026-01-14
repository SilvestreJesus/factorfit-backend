<?php

namespace App\Http\Controllers;

use App\Models\Entrenamientos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; 
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class EntrenamientosController extends Controller
{
    public function index(Request $request)
    {
        $query = Entrenamientos::latest();

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
        'titulo'      => 'required|string|max:150',
        'descripcion' => 'nullable|string',
        'sede'        => 'required|string|max:30',
        'ruta_imagen' => 'nullable|string' // Ahora es solo un string (URL)
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
        try {
            $entrenamientos = Entrenamientos::where('clave_entrenamientos', $clave)->firstOrFail();
            $urlVieja = $entrenamientos->ruta_imagen;
            $urlNueva = $request->input('ruta_imagen');

            // Solo intentamos borrar si la URL cambió y no está vacía
            if (!empty($urlNueva) && $urlNueva !== $urlVieja) {
                $this->borrarImagenCloudinary($urlVieja);
            }

            $entrenamientos->update($request->only(['titulo', 'descripcion', 'sede', 'ruta_imagen']));
            return response()->json(['message' => 'Actualizado correctamente']);
        } catch (\Exception $e) {
            Log::error("Error en Update entrenamientos: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($clave)
    {
        try {
            $entrenamientos = Entrenamientos::where('clave_entrenamientos', $clave)->firstOrFail();
            
            if ($entrenamientos->ruta_imagen) {
                $this->borrarImagenCloudinary($entrenamientos->ruta_imagen);
            }
            
            $entrenamientos->delete();
            return response()->json(['message' => 'Eliminado correctamente']);
        } catch (\Exception $e) {
            Log::error("Error en Destroy entrenamientos: " . $e->getMessage());
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
        // Esta regex extrae "entrenamientos/nombre_archivo" de la URL de Cloudinary
        if (preg_match('/upload\/v\d+\/(.+)\.[a-z]{3,4}$/i', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }



}
