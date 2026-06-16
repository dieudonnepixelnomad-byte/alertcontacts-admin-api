<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('relationships', function (Blueprint $table) {
            $table->boolean('share_position')->default(true)->after('share_level');
            $table->boolean('share_battery')->default(true)->after('share_position');
            $table->boolean('share_zone_events')->default(true)->after('share_battery');
            $table->boolean('share_speed')->default(false)->after('share_zone_events');
        });
    }

    public function down(): void
    {
        Schema::table('relationships', function (Blueprint $table) {
            $table->dropColumn(['share_position', 'share_battery', 'share_zone_events', 'share_speed']);
        });
    }
};
