<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // SQLite (used in automated tests) stores enum declarations as text.
        // MySQL needs an intermediate enum in order to preserve existing users.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY tier ENUM('free','solo','famille','premium') NOT NULL DEFAULT 'free'");
            DB::statement("ALTER TABLE subscriptions MODIFY tier ENUM('solo','famille','premium') NOT NULL");
        }

        DB::table('users')->whereIn('tier', ['solo', 'famille'])->update(['tier' => 'premium']);
        DB::table('subscriptions')->whereIn('tier', ['solo', 'famille'])->update(['tier' => 'premium']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY tier ENUM('free','premium') NOT NULL DEFAULT 'free'");
            DB::statement("ALTER TABLE subscriptions MODIFY tier ENUM('premium') NOT NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY tier ENUM('free','solo','famille','premium') NOT NULL DEFAULT 'free'");
            DB::statement("ALTER TABLE subscriptions MODIFY tier ENUM('solo','famille','premium') NOT NULL");
        }

        DB::table('users')->where('tier', 'premium')->update(['tier' => 'solo']);
        DB::table('subscriptions')->where('tier', 'premium')->update(['tier' => 'solo']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY tier ENUM('free','solo','famille') NOT NULL DEFAULT 'free'");
            DB::statement("ALTER TABLE subscriptions MODIFY tier ENUM('solo','famille') NOT NULL");
        }
    }
};
