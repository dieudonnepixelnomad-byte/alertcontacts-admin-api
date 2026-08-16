<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CDC V4.1 §7.3 — Trajets
 *
 * La bbox est indexée avec le statut : le job de surveillance (§5.5) part de
 * l'incident et cherche les trajets actifs qui le recoupent, jamais l'inverse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->decimal('origin_lat', 10, 7);
            $table->decimal('origin_lng', 10, 7);
            $table->string('origin_label', 255)->nullable();
            $table->decimal('destination_lat', 10, 7);
            $table->decimal('destination_lng', 10, 7);
            $table->string('destination_label', 255)->nullable();

            $table->enum('transport_mode', ['car', 'pedestrian', 'scooter'])->default('car');
            $table->text('polyline');                        // Flexible Polyline HERE
            $table->json('alternatives')->nullable();        // itinéraires proposés, index 0 = sélectionné
            $table->unsignedSmallInteger('selected_index')->default(0);
            $table->unsignedInteger('distance_m')->nullable();
            $table->unsignedInteger('duration_s')->nullable();

            $table->boolean('avoidance_applied')->default(false);
            $table->boolean('avoidance_partial')->default(false); // violatedBlockedRoad détecté
            $table->json('avoided_incident_ids')->nullable();

            $table->decimal('bbox_north', 10, 7)->nullable();
            $table->decimal('bbox_south', 10, 7)->nullable();
            $table->decimal('bbox_east', 10, 7)->nullable();
            $table->decimal('bbox_west', 10, 7)->nullable();

            $table->enum('status', ['planned', 'active', 'completed', 'cancelled'])->default('planned');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'user_id'], 'idx_active');
            $table->index(
                ['status', 'bbox_south', 'bbox_north', 'bbox_west', 'bbox_east'],
                'idx_routes_active_bbox'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routes');
    }
};
