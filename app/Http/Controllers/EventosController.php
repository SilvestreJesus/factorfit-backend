<?php

namespace App\Http\Controllers;

use App\Models\Eventos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; 
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class EventosController extends Controller
{
    public function index(Request $request)
    {
        $query = Eventos::latest();

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
        'titulo'      => 'required|string|max:150',
        'descripcion' => 'nullable|string',
        'sede'        => 'required|string|max:30',
        'ruta_imagen' => 'nullable|string' // Ahora es solo un string (URL)
    ]);

    DB::transaction(function () use (&$eventos, $validated) {
        $lastNumber = DB::table('eventos')
            ->selectRaw("MAX(CAST(SUBSTRING(clave_eventos FROM 5) AS INTEGER)) AS max_num")
            ->value('max_num');

        $newNumber = $lastNumber ? $lastNumber + 1 : 1;
        $validated['clave_eventos'] = 'EVEN' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);

        $eventos = Eventos::create($validated);
    });

    return response()->json(['message' => 'Evento guardado', 'eventos' => $eventos], 201);
}


public function update(Request $request, $clave)
    {
        try {
            $evento = Eventos::where('clave_eventos', $clave)->firstOrFail();
            $urlVieja = $evento->ruta_imagen;
            $urlNueva = $request->input('ruta_imagen');

            // Solo intentamos borrar si la URL cambió y no está vacía
            if (!empty($urlNueva) && $urlNueva !== $urlVieja) {
                $this->borrarImagenCloudinary($urlVieja);
            }

            $evento->update($request->only(['titulo', 'descripcion', 'sede', 'ruta_imagen']));
            return response()->json(['message' => 'Actualizado correctamente']);
        } catch (\Exception $e) {
            Log::error("Error en Update Eventos: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($clave)
    {
        try {
            $evento = Eventos::where('clave_eventos', $clave)->firstOrFail();
            
            if ($evento->ruta_imagen) {
                $this->borrarImagenCloudinary($evento->ruta_imagen);
            }
            
            $evento->delete();
            return response()->json(['message' => 'Eliminado correctamente']);
        } catch (\Exception $e) {
            Log::error("Error en Destroy Eventos: " . $e->getMessage());
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
        // Esta regex extrae "eventos/nombre_archivo" de la URL de Cloudinary
        if (preg_match('/upload\/v\d+\/(.+)\.[a-z]{3,4}$/i', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }


}