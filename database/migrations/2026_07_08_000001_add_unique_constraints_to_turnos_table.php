<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasSlotKey = Schema::hasColumn('turnos', 'slot_key');
        $hasExternalReference = Schema::hasColumn('turnos', 'external_reference');
        $hasSlotKeyUnique = Schema::hasIndex('turnos', 'turnos_slot_key_unique');
        $hasExternalReferenceUnique = Schema::hasIndex('turnos', 'turnos_external_reference_unique');

        Schema::table('turnos', function (Blueprint $table) use ($hasSlotKey, $hasExternalReference, $hasSlotKeyUnique, $hasExternalReferenceUnique) {
            if (!$hasSlotKey) {
                $table->string('slot_key')
                    ->nullable()
                    ->storedAs("CASE WHEN LOWER(estado) <> 'cancelado' THEN CONCAT(fecha, '#', hora) ELSE NULL END");
            }

            if (!$hasSlotKeyUnique) {
                $table->unique('slot_key', 'turnos_slot_key_unique');
            }

            if ($hasExternalReference && !$hasExternalReferenceUnique) {
                $table->unique('external_reference', 'turnos_external_reference_unique');
            }
        });
    }

    public function down(): void
    {
        $hasSlotKey = Schema::hasColumn('turnos', 'slot_key');
        $hasExternalReference = Schema::hasColumn('turnos', 'external_reference');
        $hasSlotKeyUnique = Schema::hasIndex('turnos', 'turnos_slot_key_unique');
        $hasExternalReferenceUnique = Schema::hasIndex('turnos', 'turnos_external_reference_unique');

        Schema::table('turnos', function (Blueprint $table) use ($hasSlotKey, $hasExternalReference, $hasSlotKeyUnique, $hasExternalReferenceUnique) {
            if ($hasSlotKeyUnique) {
                $table->dropUnique('turnos_slot_key_unique');
            }

            if ($hasExternalReference && $hasExternalReferenceUnique) {
                $table->dropUnique('turnos_external_reference_unique');
            }

            if ($hasSlotKey) {
                $table->dropColumn('slot_key');
            }
        });
    }
};
