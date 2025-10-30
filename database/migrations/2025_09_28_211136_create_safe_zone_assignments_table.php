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
        Schema::create('safe_zone_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('safe_zone_id')->constrained()->onDelete('cascade');
            
            // Nouvelle structure avec assigned_user_id au lieu de contact_id
            $table->foreignId('assigned_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_by_user_id')->constrained('users')->onDelete('cascade');
            
            // Statut et notifications
            $table->boolean('is_active')->default(true);
            $table->boolean('notify_entry')->default(true);
            $table->boolean('notify_exit')->default(true);
            $table->json('notification_settings')->nullable(); // Paramètres avancés de notification
            
            // Timestamps d'assignation et d'acceptation
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('last_notification_at')->nullable();
            
            $table->timestamps();
            
            // Contraintes d'unicité
            $table->unique(['safe_zone_id', 'assigned_user_id'], 'unique_safe_zone_user_assignment');
            
            // Index pour les performances
            $table->index('assigned_user_id');
            $table->index('assigned_by_user_id');
            $table->index(['safe_zone_id', 'is_active']);
            $table->index('assigned_at');
            $table->index('accepted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safe_zone_assignments');
    }
};