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
        // Supprimer la colonne danger_type existante
        Schema::table('danger_zones', function (Blueprint $table) {
            $table->dropIndex(['danger_type']);
            $table->dropColumn('danger_type');
        });

        // Recréer la colonne avec les bonnes valeurs
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
            ])->default('autre')->after('severity');
            
            // Index pour les requêtes par type de danger
            $table->index('danger_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Supprimer la colonne danger_type corrigée
        Schema::table('danger_zones', function (Blueprint $table) {
            $table->dropIndex(['danger_type']);
            $table->dropColumn('danger_type');
        });

        // Recréer la colonne avec les anciennes valeurs
        Schema::table('danger_zones', function (Blueprint $table) {
            $table->enum('danger_type', [
                'agression',
                'vol',
                'braquage',
                'harcelement',
                'vandalisme',
                'trafic_drogue',
                'zone_non_eclairee',
                'zone_marecageuse',
                'chantier_dangereux',
                'route_dangereuse',
                'pont_instable',
                'zone_inondable',
                'glissement_terrain',
                'zone_polluee',
                'presence_animaux',
                'autre'
            ])->default('autre')->after('severity');
            
            $table->index('danger_type');
        });
    }
};
