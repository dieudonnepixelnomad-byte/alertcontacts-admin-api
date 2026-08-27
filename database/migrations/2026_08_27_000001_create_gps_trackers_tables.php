<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gps_trackers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('external_identifier')->nullable();
            $table->enum('status', ['draft', 'active', 'suspended', 'offline'])->default('draft');
            $table->timestamp('last_position_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_identifier']);
            $table->index(['owner_id', 'status']);
        });

        Schema::create('tracker_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracker_id')->constrained('gps_trackers')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->float('accuracy')->nullable();
            $table->float('speed')->nullable();
            $table->float('heading')->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->timestamp('captured_at_device');
            $table->timestamp('received_at')->useCurrent();
            $table->string('source', 50)->default('simulator');
            $table->timestamps();
            $table->index(['tracker_id', 'captured_at_device']);
        });

        Schema::create('tracker_safe_zone_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracker_id')->constrained('gps_trackers')->cascadeOnDelete();
            $table->foreignId('safe_zone_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('notify_entry')->default(true);
            $table->boolean('notify_exit')->default(true);
            $table->timestamps();
            $table->unique(['tracker_id', 'safe_zone_id']);
        });

        Schema::create('tracker_safe_zone_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracker_id')->constrained('gps_trackers')->cascadeOnDelete();
            $table->foreignId('safe_zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracker_location_id')->constrained('tracker_locations')->cascadeOnDelete();
            $table->enum('event_type', ['entry', 'exit']);
            $table->float('distance_m')->nullable();
            $table->timestamp('occurred_at');
            $table->boolean('notification_sent')->default(false);
            $table->timestamps();
            $table->index(
                ['tracker_id', 'safe_zone_id', 'occurred_at'],
                'tracker_zone_event_lookup_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracker_safe_zone_events');
        Schema::dropIfExists('tracker_safe_zone_assignments');
        Schema::dropIfExists('tracker_locations');
        Schema::dropIfExists('gps_trackers');
    }
};
