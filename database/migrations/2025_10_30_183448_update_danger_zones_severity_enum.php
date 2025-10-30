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
        // Modifier la colonne severity pour utiliser les valeurs compatibles avec Flutter
        Schema::table('danger_zones', function (Blueprint $table) {
            // Supprimer l'ancienne contrainte enum et recréer avec les bonnes valeurs
            $table->dropColumn('severity');
        });
        
        Schema::table('danger_zones', function (Blueprint $table) {
            // Recréer la colonne avec les bonnes valeurs enum
            $table->enum('severity', ['low', 'med', 'high'])->default('med')->after('danger_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('danger_zones', function (Blueprint $table) {
            $table->dropColumn('severity');
        });
        
        Schema::table('danger_zones', function (Blueprint $table) {
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium')->after('danger_type');
        });
    }
};
