<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CDC V4.1 §7.2 — Incidents
 *
 * Objet publié, affiché et routé. Agrège 1 à N signalements (§4.5).
 *
 * Les trois rayons sont découplés (§4.1) :
 *   danger_buffer_m   → ÉVITEMENT au routage (chirurgical, 15-60 m)
 *   notify_radius_m   → qui reçoit le PUSH (généreux, 200-2000 m)
 *   display_radius_m  → halo dessiné sur la carte, exprime l'incertitude (§4.4)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->enum('severity', ['low', 'medium', 'high']); // max des signalements

            // Géométrie — §4.2
            $table->enum('geometry_type', ['corridor', 'polygon']);
            $table->json('geometry');                               // polyligne ou anneau extérieur
            $table->unsignedSmallInteger('danger_buffer_m')->default(20);
            $table->unsignedSmallInteger('notify_radius_m')->default(500);
            $table->unsignedSmallInteger('display_radius_m')->default(200);

            // Position et indexation
            $table->decimal('centroid_lat', 10, 7);
            $table->decimal('centroid_lng', 10, 7);
            $table->decimal('bbox_north', 10, 7);
            $table->decimal('bbox_south', 10, 7);
            $table->decimal('bbox_east', 10, 7);
            $table->decimal('bbox_west', 10, 7);

            // Confiance — §4.8
            $table->unsignedSmallInteger('report_count')->default(1);  // = confiance
            $table->unsignedSmallInteger('confirm_count')->default(0); // « je le vois aussi »
            $table->unsignedSmallInteger('clear_count')->default(0);   // « c'est terminé »
            $table->decimal('confidence_score', 3, 2)->default(0);

            // Routage — booléen dérivé de RouteAvoidancePolicy (§4.9 + §4.10)
            $table->boolean('affects_routing')->default(false);

            // Cycle de vie — §4.7
            $table->enum('status', ['active', 'resolved', 'expired', 'rejected'])->default('active');
            $table->timestamp('expires_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(
                ['status', 'expires_at', 'bbox_south', 'bbox_north', 'bbox_west', 'bbox_east'],
                'idx_active_bbox'
            );
            $table->index(['affects_routing', 'status', 'expires_at'], 'idx_routing');
        });

        // FK depuis alert_reports — SET NULL : la purge d'un incident ne détruit
        // pas les signalements bruts, qui ont leur propre rétention.
        Schema::table('alert_reports', function (Blueprint $table) {
            $table->foreign('incident_id')
                ->references('id')->on('incidents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alert_reports', function (Blueprint $table) {
            $table->dropForeign(['incident_id']);
        });

        Schema::dropIfExists('incidents');
    }
};
