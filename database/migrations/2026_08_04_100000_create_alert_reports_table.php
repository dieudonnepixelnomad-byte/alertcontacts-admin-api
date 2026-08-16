<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CDC V4.1 §7.1 — Signalements
 *
 * Un signalement est ce qu'un utilisateur envoie : donnée brute, jamais publiée
 * telle quelle. Le clustering (§4.5) le rattache ensuite à un incident.
 * La FK vers incidents est ajoutée dans la migration des incidents, qui suit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('incident_id')->nullable(); // rattaché après clustering
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->enum('severity', ['low', 'medium', 'high']);

            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->unsignedSmallInteger('gps_accuracy_m')->nullable(); // précision du fix (§4.8)
            $table->json('gps_trace')->nullable();                      // 100 derniers m → corridor (§4.6)
            $table->boolean('was_moving')->default(false);
            $table->unsignedSmallInteger('speed_kmh')->nullable();

            $table->text('comment')->nullable();
            $table->string('photo_url', 255)->nullable();
            $table->enum('visibility', ['public', 'circle'])->default('public');

            $table->timestamps();

            $table->index(['type', 'created_at', 'lat', 'lng'], 'idx_cluster');
            $table->index('incident_id', 'idx_incident');
            $table->index(['user_id', 'created_at'], 'idx_user_rate'); // plafond §4.10 règle 4
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_reports');
    }
};
