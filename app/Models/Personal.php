<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Personal extends Model
{
    protected $table = 'personal';
    protected $primaryKey = 'clave_personal';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'clave_personal',
        'nombre_completo',
        'puesto',
        'descripcion',
        'ruta_imagen',
        'sede',
        'rol'
    ];
}
