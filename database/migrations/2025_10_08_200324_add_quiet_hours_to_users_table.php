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
        Schema::table('users', function (Blueprint $table) {
            $table->time('quiet_hours_start')->nullable()->default('22:00')->comment('Heure de début des heures calmes');
            $table->time('quiet_hours_end')->nullable()->default('07:00')->comment('Heure de fin des heures calmes');
            $table->string('timezone', 50)->nullable()->default('UTC')->comment('Fuseau horaire de l\'utilisateur');
            $table->boolean('quiet_hours_enabled')->default(true)->comment('Activer/désactiver les heures calmes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['quiet_hours_start', 'quiet_hours_end', 'timezone', 'quiet_hours_enabled']);
        });
    }
};
