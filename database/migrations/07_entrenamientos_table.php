<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('entrenamientos', function (Blueprint $table) {
            $table->string('clave_entrenamientos', 20)->primary();
            $table->string('titulo', 150);
            $table->text('descripcion')->nullable();
            $table->text('ruta_imagen')->nullable();
            $table->string('sede', 12);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('entrenamientos');
    }
};
