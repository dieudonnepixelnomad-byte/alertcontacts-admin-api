<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('location_consent')->nullable()->after('invisible_until');
            $table->boolean('notification_consent')->nullable()->after('location_consent');
            $table->boolean('analytics_consent')->nullable()->after('notification_consent');
            $table->timestamp('consents_updated_at')->nullable()->after('analytics_consent');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'location_consent',
                'notification_consent',
                'analytics_consent',
                'consents_updated_at',
            ]);
        });
    }
};
