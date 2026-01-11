<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Usuario extends Model
{
    use HasFactory;

    protected $table = 'usuarios';
    protected $primaryKey = 'clave_usuario';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'clave_usuario',
        'nombres',
        'apellidos',
        'fecha_nacimiento',
        'telefono',
        'email',
        'password',
        'sede',
        'status',
        'peso_inicial',
        'ruta_imagen',
        'qr_imagen',
        'rol',
        'fecha_inscripcion',
        'fecha_corte',
        'tipo_pago'
        
    ];


    public function pago() // Cambiado de pagos a pago
    {
        // Usamos hasOne para obtener el registro de pago más reciente
        return $this->hasOne(Pago::class, 'clave_cliente', 'clave_usuario')->latestOfMany();
    }


    public function pagos()
    {
        return $this->hasMany(Pago::class, 'clave_cliente', 'clave_usuario');
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class, 'clave_cliente', 'clave_usuario');
    }


    
}
