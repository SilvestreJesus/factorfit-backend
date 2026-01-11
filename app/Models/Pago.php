<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos';

    protected $fillable = [
        'clave_cliente',
        'fecha_ingreso',
        'fecha_corte',
        'Tipo_pago',
        'monto_pagado',
        'monto_pendiente',
        'monto_recargo'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'clave_cliente', 'clave_usuario');
    }
}