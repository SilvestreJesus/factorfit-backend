<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    use HasFactory;

    protected $table = 'asistencias';

    protected $fillable = [
        'clave_cliente',
        'fecha_diario',
        'porcentaje'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'clave_cliente', 'clave_usuario');
    }
}
