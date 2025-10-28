<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-A1: Migration pour la table des positions GPS des utilisateurs
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Coordonnées GPS
            $table->decimal('latitude', 10, 8); // Précision ~1cm
            $table->decimal('longitude', 11, 8); // Précision ~1cm
            
            // Métadonnées de position
            $table->decimal('accuracy', 8, 2)->nullable(); // Précision en mètres
            $table->decimal('speed', 8, 2)->nullable(); // Vitesse en m/s
            $table->decimal('heading', 5, 2)->nullable(); // Direction en degrés (0-360)
            
            // Timestamps
            $table->timestamp('captured_at_device'); // Timestamp du device
            $table->timestamps(); // created_at, updated_at
            
            // Métadonnées techniques
            $table->enum('source', ['gps', 'network', 'passive', 'fused'])->default('gps');
            $table->boolean('foreground')->default(true); // App en premier plan ou arrière-plan
            $table->tinyInteger('battery_level')->nullable(); // Niveau batterie (0-100)
            
            // Index pour les performances
            $table->index(['user_id', 'captured_at_device']);
            $table->index(['user_id', 'created_at']);
            $table->index('captured_at_device');
            
            // Index géospatial pour les requêtes de proximité
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_locations');
    }
};