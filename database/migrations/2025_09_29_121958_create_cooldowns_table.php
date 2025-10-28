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
        Schema::create('cooldowns', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->index(); // Clé unique du cooldown (ex: danger_zone_1_user_123)
            $table->timestamp('expires_at')->index(); // Date d'expiration du cooldown
            $table->json('metadata')->nullable(); // Métadonnées optionnelles (zone_id, user_id, type, etc.)
            $table->timestamps();
        });

        // Index composé pour optimiser les requêtes de nettoyage
        Schema::table('cooldowns', function (Blueprint $table) {
            $table->index(['expires_at', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cooldowns');
    }
};