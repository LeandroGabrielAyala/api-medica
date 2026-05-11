<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recetas', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('medico_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('motivo');

            $table->string('medicamento')->nullable();

            $table->boolean('urgente')
                ->default(false);

            $table->text('indicaciones')
                ->nullable();

            $table->string('archivo')
                ->nullable();

            $table->string('estado')
                ->default('Pendiente');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recetas');
    }
};