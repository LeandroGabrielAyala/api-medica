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
        Schema::create('pagos', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('modulo');

            $table->unsignedBigInteger('modulo_id')
                ->nullable();

            $table->decimal('monto', 10, 2);

            $table->string('estado')
                ->default('pendiente');

            $table->string('external_reference')
                ->unique();

            $table->string('mp_payment_id')
                ->nullable();

            $table->timestamp('fecha_pago')
                ->nullable();

            $table->json('metadata')
                ->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
