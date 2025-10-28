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
        Schema::create('pending_safe_zone_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('safe_zone_id')->constrained()->onDelete('cascade');
            $table->foreignId('safe_zone_event_id')->constrained()->onDelete('cascade');
            $table->timestamp('first_alert_sent_at');
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->integer('reminder_count')->default(0);
            $table->boolean('confirmed')->default(false);
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->json('metadata')->nullable(); // Pour stocker des infos supplémentaires
            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->index(['user_id', 'safe_zone_id']);
            $table->index(['confirmed', 'last_reminder_sent_at']);
            $table->index('first_alert_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_safe_zone_alerts');
    }
};