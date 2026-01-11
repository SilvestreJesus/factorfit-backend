<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pagos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('clave_cliente', 8); 
            $table->timestamp('fecha_ingreso')->nullable();
            $table->timestamp('fecha_corte')->nullable();
            $table->string('Tipo_pago',20)->nullable();
            $table->float('monto_pagado')->nullable();
            $table->float('monto_pendiente')->nullable();
            $table->float('monto_recargo')->nullable();

            $table->timestamps();

            $table->foreign('clave_cliente')->references('clave_usuario')->on('usuarios')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('pagos');
    }
};
