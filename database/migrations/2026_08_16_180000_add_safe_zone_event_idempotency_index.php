<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('safe_zone_events', function (Blueprint $table) {
            // A retried location batch must not produce the same transition twice.
            $table->unique(
                ['user_id', 'safe_zone_id', 'event_type', 'captured_at_device'],
                'safe_zone_events_transition_idempotency',
            );
        });
    }

    public function down(): void
    {
        Schema::table('safe_zone_events', function (Blueprint $table) {
            $table->dropUnique('safe_zone_events_transition_idempotency');
        });
    }
};
