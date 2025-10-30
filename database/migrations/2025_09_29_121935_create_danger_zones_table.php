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
        Schema::create('danger_zones', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            
            // Géométrie avec gestion du SRID selon la base de données
            if (DB::getDriverName() === 'sqlite') {
                // SQLite ne supporte pas les colonnes géométriques natives
                $table->decimal('center_lat', 10, 8);
                $table->decimal('center_lng', 11, 8);
            } else {
                // MySQL/MariaDB avec support spatial
                $table->point('center')->spatialIndex();
            }
            
            $table->integer('radius_m')->default(100); // Rayon en mètres
            
            // Type de danger avec enum étendu
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
            ])->default('autre');
            
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->integer('confirmations')->default(1);
            $table->timestamp('last_report_at')->useCurrent();
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Index
            $table->index(['is_active', 'severity']);
            $table->index('last_report_at');
            $table->index('reported_by');
            $table->index('danger_type');
            
            if (DB::getDriverName() !== 'sqlite') {
                $table->index('center');
            } else {
                $table->index(['center_lat', 'center_lng']);
            }
        });
        
        // Correction du SRID pour MySQL 8.0+ si nécessaire
        if (DB::getDriverName() === 'mysql') {
            $version = DB::select('SELECT VERSION() as version')[0]->version;
            $isMySQL8Plus = version_compare($version, '8.0.0', '>=') && !str_contains(strtolower($version), 'mariadb');
            
            if ($isMySQL8Plus) {
                try {
                    // Vérifier si la colonne existe et a le bon SRID
                    $result = DB::select("
                        SELECT COLUMN_NAME, SRS_ID 
                        FROM INFORMATION_SCHEMA.ST_GEOMETRY_COLUMNS 
                        WHERE TABLE_NAME = 'danger_zones' 
                        AND COLUMN_NAME = 'center'
                    ");
                    
                    if (!empty($result) && $result[0]->SRS_ID != 4326) {
                        // Supprimer l'index spatial temporairement
                        DB::statement('DROP INDEX center ON danger_zones');
                        
                        // Modifier le SRID
                        DB::statement('ALTER TABLE danger_zones MODIFY center POINT SRID 4326');
                        
                        // Recréer l'index spatial
                        DB::statement('CREATE SPATIAL INDEX center ON danger_zones (center)');
                    }
                } catch (\Exception $e) {
                    // Ignorer les erreurs de SRID en cas de problème
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('danger_zones');
    }
};