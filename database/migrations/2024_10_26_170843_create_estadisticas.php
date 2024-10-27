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
        Schema::create('estadisticas', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('jyv')->nullable();
            $table->string('badmail', 30)->nullable();
            $table->string('baja', 4)->nullable();
            $table->dateTime('fecha_envio')->nullable();
            $table->dateTime('fecha_open')->nullable();
            $table->unsignedInteger('opens')->nullable();
            $table->unsignedInteger('opens_virales')->nullable();
            $table->dateTime('fecha_click')->nullable();
            $table->unsignedInteger('clicks')->nullable();
            $table->unsignedInteger('clicks_virales')->nullable();
            $table->string('links', 30)->nullable();
            $table->string('ips')->nullable();
            $table->string('navegadores')->nullable();
            $table->string('plataformas')->nullable();
            $table->timestamps();

            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estadisticas');
    }
};
