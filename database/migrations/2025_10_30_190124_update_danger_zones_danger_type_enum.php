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
        // Modifier l'énumération danger_type pour inclure toutes les valeurs utilisées par Flutter
        Schema::table('danger_zones', function (Blueprint $table) {
            $table->enum('danger_type', [
                'agression',
                'vol',
                'braquage',
                'harcelement',
                'zone_non_eclairee',
                'zone_marecageuse',
                'accident_frequent',
                'deal_drogue',
                'vandalisme',
                'zone_deserte',
                'construction_dangereuse',
                'animaux_errants',
                'manifestation',
                'inondation',
                'autre'
            ])->default('autre')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revenir à l'énumération précédente
        Schema::table('danger_zones', function (Blueprint $table) {
            $table->enum('danger_type', [
                'agression',
                'vol',
                'accident',
                'vandalisme',
                'trafic_drogue',
                'zone_sensible',
                'manifestation',
                'travaux',
                'inondation',
                'autre'
            ])->default('autre')->change();
        });
    }
};
