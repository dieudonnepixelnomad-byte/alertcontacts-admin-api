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
            
            // Index pour les requêtes par type de danger
            $table->index('danger_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('danger_zones', function (Blueprint $table) {
            $table->dropIndex(['danger_type']);
            $table->dropColumn('danger_type');
        });
    }
};
