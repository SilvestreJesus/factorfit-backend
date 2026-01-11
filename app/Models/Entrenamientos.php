<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entrenamientos extends Model
{
    protected $table = 'entrenamientos';
    protected $primaryKey = 'clave_entrenamientos';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'clave_entrenamientos',
        'titulo',
        'descripcion',
        'ruta_imagen',
        'sede',
    ];
}
