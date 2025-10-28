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
        Schema::create('danger_zone_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('danger_zone_id')->constrained('danger_zones')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('reason', 500);
            $table->timestamp('reported_at');
            $table->timestamps();

            // Un utilisateur ne peut signaler qu'une seule fois une zone
            $table->unique(['danger_zone_id', 'user_id']);
            // Index pour les requêtes par zone
            $table->index('danger_zone_id');
            // Index pour les requêtes par utilisateur
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('danger_zone_reports');
    }
};
