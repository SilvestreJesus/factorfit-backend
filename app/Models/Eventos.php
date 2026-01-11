<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Eventos extends Model
{
    protected $table = 'eventos';
    protected $primaryKey = 'clave_eventos';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'clave_eventos',
        'titulo',
        'descripcion',
        'ruta_imagen',
        'sede',
    ];
}
