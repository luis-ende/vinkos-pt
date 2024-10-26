<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('visitante', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->dateTime('fecha_primera_visita');
            $table->dateTime('fecha_ultima_visita');
            $table->unsignedBigInteger('visitas_totales')->default(0);
            $table->unsignedBigInteger('visitas_anio_total')->default(0);
            $table->unsignedInteger('visitas_mes_actual')->unique(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitante');
    }
};
