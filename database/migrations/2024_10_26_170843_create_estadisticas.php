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
        Schema::create('estadistica', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('visitante_id');
            $table->string('jk');
            $table->string('badmail');
            $table->string('baja', 4);
            $table->dateTime('fecha_envio');
            $table->dateTime('fecha_open');
            $table->unsignedInteger('opens');
            $table->unsignedInteger('opens_virales');
            $table->dateTime('fecha_click');
            $table->unsignedInteger('clicks');
            $table->unsignedInteger('clicks_virales');
            $table->unsignedInteger('links');
            $table->ipAddress('ips');
            $table->string('navegadores');
            $table->string('plataformas');
            $table->timestamps();

            $table->foreign('visitante_id')->references('id')->on('visitante');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estadistica');
    }
};
