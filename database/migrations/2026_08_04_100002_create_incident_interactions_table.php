<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interactions utilisateur → incident (confirm / clear / report-abuse).
 *
 * L'unicité (incident_id, user_id, action) rend les compteurs idempotents :
 * un même utilisateur ne peut plus gonfler confirm_count en tapant deux fois,
 * défaut du modèle V4.0 (AlertController@confirm incrémentait sans condition).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('action', ['confirm', 'clear', 'report']);
            $table->string('reason', 200)->nullable(); // motif du signalement d'abus
            $table->timestamps();

            $table->unique(['incident_id', 'user_id', 'action'], 'uniq_incident_user_action');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_interactions');
    }
};
