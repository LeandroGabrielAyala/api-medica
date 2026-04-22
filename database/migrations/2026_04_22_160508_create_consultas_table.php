<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('consultas', function (Blueprint $table) {

            $table->id();

            $table->string('tipo');

            $table->decimal('monto', 10, 2);

            $table->string('metodo_pago')->nullable();

            $table->string('estado')
                  ->default('pendiente_pago');

            $table->string('external_reference')
                  ->nullable();

            $table->timestamp('fecha_pago')
                  ->nullable();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultas');
    }

};