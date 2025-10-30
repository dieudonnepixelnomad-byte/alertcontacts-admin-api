<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable(); // Nullable pour les connexions via Firebase
            
            // Champs Firebase
            $table->string('firebase_uid')->nullable()->unique();
            $table->string('provider')->default('email'); // email, google, apple
            $table->string('avatar_url')->nullable();
            $table->string('phone_number')->nullable();
            
            // Champs FCM (Firebase Cloud Messaging)
            $table->string('fcm_token', 500)->nullable();
            $table->timestamp('fcm_token_updated_at')->nullable();
            $table->string('fcm_platform')->nullable(); // ios, android, web
            
            // Heures de silence
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->string('timezone')->default('Europe/Paris');
            $table->boolean('quiet_hours_enabled')->default(false);
            
            // Administration
            $table->boolean('is_admin')->default(false);
            
            $table->rememberToken();
            $table->timestamps();
            
            // Index
            $table->index('fcm_token');
            $table->index('firebase_uid');
            $table->index('provider');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};