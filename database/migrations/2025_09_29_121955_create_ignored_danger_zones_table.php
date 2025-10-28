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
        Schema::create('ignored_danger_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('danger_zone_id')->constrained()->onDelete('cascade');
            $table->timestamp('ignored_at');
            $table->timestamp('expires_at')->nullable(); // Expiration automatique après 6 mois
            $table->string('reason')->nullable(); // Raison de l'ignorage (optionnel)
            $table->timestamps();

            // Index pour les requêtes fréquentes
            $table->index(['user_id', 'danger_zone_id']);
            $table->index(['user_id', 'expires_at']);
            
            // Contrainte unique : un utilisateur ne peut ignorer qu'une fois la même zone
            $table->unique(['user_id', 'danger_zone_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ignored_danger_zones');
    }
};