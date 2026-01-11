<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('promociones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Ej: "Paquete 3 meses"
            $table->integer('meses');  // Cuántos meses otorga
            $table->double('precio'); // Cuánto cuesta
            $table->string('sede');   // Para que cada gimnasio tenga sus propias promos
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promocions');
    }
};
