<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'onboarding_completed')) {
                $table->boolean('onboarding_completed')->default(false)->index();
            }
            if (!Schema::hasColumn('users', 'onboarding_data')) {
                $table->json('onboarding_data')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'onboarding_completed')) {
                $table->dropColumn('onboarding_completed');
            }
            if (Schema::hasColumn('users', 'onboarding_data')) {
                $table->dropColumn('onboarding_data');
            }
        });
    }
};