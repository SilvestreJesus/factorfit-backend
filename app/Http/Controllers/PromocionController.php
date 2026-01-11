<?php
namespace App\Http\Controllers;

use App\Models\Promocion;
use Illuminate\Http\Request;

class PromocionController extends Controller
{
    public function index(Request $request) {
        // Filtra promociones por la sede del usuario/gimnasio
        return Promocion::where('sede', $request->sede)->get();
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'nombre' => 'required|string',
            'meses' => 'required|integer',
            'precio' => 'required|numeric',
            'sede' => 'required|string',
        ]);

        return Promocion::create($validated);
    }

    public function destroy($id) {
        Promocion::destroy($id);
        return response()->json(['message' => 'Eliminado']);
    }
}