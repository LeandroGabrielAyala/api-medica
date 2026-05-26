<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultas', function (Blueprint $table) {

            $table->foreignId('medico_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('fecha_inicio')
                ->nullable();

            $table->timestamp('fecha_finalizacion')
                ->nullable();

            $table->text('observaciones_medicas')
                ->nullable();

        });
    }

    public function down(): void
    {
        Schema::table('consultas', function (Blueprint $table) {

            $table->dropForeign(['medico_id']);

            $table->dropColumn([
                'medico_id',
                'fecha_inicio',
                'fecha_finalizacion',
                'observaciones_medicas'
            ]);

        });
    }
};