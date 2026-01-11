<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Instalaciones extends Model
{
    protected $table = 'instalaciones';
    protected $primaryKey = 'clave_instalaciones';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'clave_instalaciones',
        'titulo',
        'descripcion',
        'ruta_imagen',
        'sede',
    ];
}
