<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            $table->string('tipo')->default('Turno Médico');

            $table->decimal('monto', 10, 2);

            $table->string('estado')
                ->default('pendiente');

            $table->string('metodo_pago')
                ->nullable();

            $table->string('external_reference')
                ->nullable();

            $table->timestamp('fecha_pago')
                ->nullable();

            $table->date('fecha');

            $table->string('hora');

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};