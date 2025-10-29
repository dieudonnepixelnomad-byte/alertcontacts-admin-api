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

        // Vérifier si nous sommes sur MariaDB ou MySQL < 8.0
        $version = DB::select('SELECT VERSION() as version')[0]->version;
        $isMariaDB = str_contains(strtolower($version), 'mariadb');
        $isMySQLOld = !$isMariaDB && version_compare($version, '8.0', '<');

        if ($isMariaDB || $isMySQLOld) {
            // Pour MariaDB et MySQL < 8.0, on ne peut pas utiliser SRID dans ALTER TABLE
            // On se contente de mettre à jour les données existantes
            try {
                // Corriger toutes les géométries existantes avec SRID 0 vers SRID 4326
                DB::statement('UPDATE danger_zones SET center = ST_GeomFromText(ST_AsText(center), 4326) WHERE ST_SRID(center) = 0');
            } catch (\Exception $e) {
                // Si la fonction ST_SRID n'existe pas, on ignore cette étape
                // Les données seront gérées au niveau applicatif
            }
            
            // Vérifier si l'index spatial existe avant de le supprimer
            $indexExists = DB::select("SHOW INDEX FROM danger_zones WHERE Key_name = 'danger_zones_center_spatialindex'");
            if (!empty($indexExists)) {
                DB::statement('DROP INDEX danger_zones_center_spatialindex ON danger_zones');
            }
            
            // Pour MariaDB, on ne modifie pas la structure de la colonne
            // Le SRID sera géré au niveau applicatif
            
            // Recréer l'index spatial
            DB::statement('CREATE SPATIAL INDEX danger_zones_center_spatialindex ON danger_zones (center)');
        } else {
            // Pour MySQL 8.0+, on peut utiliser la syntaxe complète
            // Corriger toutes les géométries existantes avec SRID 0 vers SRID 4326
            DB::statement('UPDATE danger_zones SET center = ST_GeomFromText(ST_AsText(center), 4326) WHERE ST_SRID(center) = 0');
            
            // Supprimer l'index spatial existant
            DB::statement('DROP INDEX danger_zones_center_spatialindex ON danger_zones');
            
            // Modifier la colonne pour spécifier explicitement le SRID 4326
            DB::statement('ALTER TABLE danger_zones MODIFY center GEOMETRY NOT NULL SRID 4326');
            
            // Recréer l'index spatial
            DB::statement('CREATE SPATIAL INDEX danger_zones_center_spatialindex ON danger_zones (center)');
        }
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

        // Vérifier si nous sommes sur MariaDB ou MySQL < 8.0
        $version = DB::select('SELECT VERSION() as version')[0]->version;
        $isMariaDB = str_contains(strtolower($version), 'mariadb');
        $isMySQLOld = !$isMariaDB && version_compare($version, '8.0', '<');

        if ($isMariaDB || $isMySQLOld) {
            // Pour MariaDB et MySQL < 8.0, on ne fait que gérer l'index
            $indexExists = DB::select("SHOW INDEX FROM danger_zones WHERE Key_name = 'danger_zones_center_spatialindex'");
            if (!empty($indexExists)) {
                DB::statement('DROP INDEX danger_zones_center_spatialindex ON danger_zones');
            }
            
            // Recréer l'index spatial
            DB::statement('CREATE SPATIAL INDEX danger_zones_center_spatialindex ON danger_zones (center)');
        } else {
            // Pour MySQL 8.0+
            // Supprimer l'index spatial
            DB::statement('DROP INDEX danger_zones_center_spatialindex ON danger_zones');
            
            // Revenir à une géométrie sans SRID spécifique
            DB::statement('ALTER TABLE danger_zones MODIFY center GEOMETRY NOT NULL');
            
            // Recréer l'index spatial
            DB::statement('CREATE SPATIAL INDEX danger_zones_center_spatialindex ON danger_zones (center)');
        }
    }
};
