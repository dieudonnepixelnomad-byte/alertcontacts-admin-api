<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('danger_zones', function (Blueprint $table) {
            $table->index(
                ['is_active', 'center_lat', 'center_lng', 'last_report_at'],
                'idx_danger_zones_viewport'
            );
            $table->index(
                ['is_active', 'severity', 'center_lat', 'center_lng', 'last_report_at'],
                'idx_danger_zones_viewport_severity'
            );
        });
    }

    public function down(): void
    {
        Schema::table('danger_zones', function (Blueprint $table) {
            $table->dropIndex('idx_danger_zones_viewport');
            $table->dropIndex('idx_danger_zones_viewport_severity');
        });
    }
};
