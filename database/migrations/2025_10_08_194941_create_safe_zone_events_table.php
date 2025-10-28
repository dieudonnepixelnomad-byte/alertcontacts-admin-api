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
        Schema::create('safe_zone_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('safe_zone_id')->constrained()->onDelete('cascade');
            $table->enum('event_type', ['entry', 'exit']);
            $table->geometry('location', 'point');
            $table->float('accuracy')->nullable();
            $table->float('distance_m')->nullable();
            $table->float('speed_kmh')->nullable();
            $table->float('heading')->nullable();
            $table->integer('battery_level')->nullable();
            $table->string('source', 50)->nullable();
            $table->boolean('foreground')->default(true);
            $table->timestamp('captured_at_device')->nullable();
            $table->boolean('notification_sent')->default(false);
            $table->timestamp('notification_sent_at')->nullable();
            $table->timestamps();

            // Index pour les requêtes fréquentes
            $table->index(['user_id', 'safe_zone_id', 'event_type']);
            $table->index(['user_id', 'created_at']);
            $table->index(['safe_zone_id', 'created_at']);
            // Index spatial (seulement si ce n'est pas SQLite)
            if (config('database.default') !== 'sqlite') {
                $table->spatialIndex('location');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safe_zone_events');
    }
};
