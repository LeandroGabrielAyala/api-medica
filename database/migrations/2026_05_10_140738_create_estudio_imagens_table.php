<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estudio_imagens', function (Blueprint $table) {

            $table->id();

            $table->foreignId('estudio_id')
                ->constrained()
                ->onDelete('cascade');

            $table->string('imagen');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estudio_imagens');
    }
};