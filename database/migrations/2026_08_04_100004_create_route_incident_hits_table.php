<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CDC V4.1 §7.4 — Rencontres trajet × incident
 *
 * « Cette table n'est pas une table technique, c'est le tableau de bord produit
 * du module » (§7.4). Elle alimente tous les indicateurs du §13 et sert de
 * compteur de quota pour le gating du §10.2.
 *
 * L'unicité (route_id, incident_id) garantit la règle §9.2 : une seule
 * notification par incident et par trajet, jamais de rappel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_incident_hits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_distance_m')->nullable();
            $table->enum('detected_phase', ['pre_departure', 'en_route']);
            $table->enum('user_action', ['avoided', 'ignored', 'no_alternative', 'not_offered'])->nullable();
            $table->boolean('notified')->default(false);
            $table->timestamp('detected_at')->useCurrent();
            $table->timestamp('acted_at')->nullable();

            $table->unique(['route_id', 'incident_id'], 'uniq_route_incident');
            $table->index('route_id', 'idx_route');
            $table->index(['detected_phase', 'user_action', 'detected_at'], 'idx_analytics');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_incident_hits');
    }
};
