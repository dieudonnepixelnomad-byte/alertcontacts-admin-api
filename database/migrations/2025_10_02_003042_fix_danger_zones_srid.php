<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cette migration ne s'applique qu'aux bases de données qui supportent les fonctions spatiales
        if (config('database.default') === 'sqlite') {
            return;
        }

        // Corriger toutes les géométries existantes avec SRID 0 vers SRID 4326
        DB::statement('UPDATE danger_zones SET center = ST_GeomFromText(ST_AsText(center), 4326) WHERE ST_SRID(center) = 0');
        
        // Supprimer l'index spatial existant
        DB::statement('DROP INDEX danger_zones_center_spatialindex ON danger_zones');
        
        // Modifier la colonne pour spécifier explicitement le SRID 4326
        DB::statement('ALTER TABLE danger_zones MODIFY center GEOMETRY NOT NULL SRID 4326');
        
        // Recréer l'index spatial
        DB::statement('CREATE SPATIAL INDEX danger_zones_center_spatialindex ON danger_zones (center)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cette migration ne s'applique qu'aux bases de données qui supportent les fonctions spatiales
        if (config('database.default') === 'sqlite') {
            return;
        }

        // Supprimer l'index spatial
        DB::statement('DROP INDEX danger_zones_center_spatialindex ON danger_zones');
        
        // Revenir à une géométrie sans SRID spécifique
        DB::statement('ALTER TABLE danger_zones MODIFY center GEOMETRY NOT NULL');
        
        // Recréer l'index spatial
        DB::statement('CREATE SPATIAL INDEX danger_zones_center_spatialindex ON danger_zones (center)');
    }
};
