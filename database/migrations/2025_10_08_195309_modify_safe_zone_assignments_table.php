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
        // Cette migration ne s'applique pas en environnement de test avec SQLite
        if (app()->environment('testing') && config('database.default') === 'sqlite') {
            return;
        }

        // Vérifier si la table existe d'abord
        if (!Schema::hasTable('safe_zone_assignments')) {
            return;
        }

        Schema::table('safe_zone_assignments', function (Blueprint $table) {
            // Supprimer les contraintes de clé étrangère d'abord
            if (Schema::hasColumn('safe_zone_assignments', 'contact_id')) {
                try {
                    $table->dropForeign(['contact_id']);
                } catch (\Exception $e) {
                    // Ignorer si la contrainte n'existe pas
                }
                try {
                    $table->dropColumn('contact_id');
                } catch (\Exception $e) {
                    // Ignorer si la colonne n'existe pas
                }
            }
            
            // Supprimer l'ancienne colonne status si elle existe
            if (Schema::hasColumn('safe_zone_assignments', 'status')) {
                $table->dropColumn('status');
            }
            
            // Ajouter les nouvelles colonnes si elles n'existent pas
            if (!Schema::hasColumn('safe_zone_assignments', 'assigned_user_id')) {
                $table->foreignId('assigned_user_id')->constrained('users')->onDelete('cascade');
            }
            
            if (!Schema::hasColumn('safe_zone_assignments', 'assigned_by_user_id')) {
                $table->foreignId('assigned_by_user_id')->constrained('users')->onDelete('cascade');
            }
            
            if (!Schema::hasColumn('safe_zone_assignments', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            
            if (!Schema::hasColumn('safe_zone_assignments', 'notify_entry')) {
                $table->boolean('notify_entry')->default(true);
            }
            
            if (!Schema::hasColumn('safe_zone_assignments', 'notify_exit')) {
                $table->boolean('notify_exit')->default(true);
            }
            
            if (!Schema::hasColumn('safe_zone_assignments', 'notification_settings')) {
                $table->json('notification_settings')->nullable();
            }
            
            if (!Schema::hasColumn('safe_zone_assignments', 'assigned_at')) {
                $table->timestamp('assigned_at')->useCurrent();
            }
            
            if (!Schema::hasColumn('safe_zone_assignments', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable();
            }
            
            if (!Schema::hasColumn('safe_zone_assignments', 'last_notification_at')) {
                $table->timestamp('last_notification_at')->nullable();
            }
        });
        
        // Ajouter les index après avoir ajouté les colonnes
        Schema::table('safe_zone_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('safe_zone_assignments', 'assigned_user_id')) {
                return; // Skip si la colonne n'existe pas
            }
            
            // Ajouter les index
            try {
                $table->index(['assigned_user_id', 'is_active']);
                $table->index(['safe_zone_id', 'is_active']);
                $table->index(['assigned_by_user_id']);
                $table->unique(['safe_zone_id', 'assigned_user_id']);
            } catch (\Exception $e) {
                // Index peuvent déjà exister
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('safe_zone_assignments', function (Blueprint $table) {
            // Supprimer les index
            try {
                $table->dropIndex(['assigned_user_id', 'is_active']);
                $table->dropIndex(['safe_zone_id', 'is_active']);
                $table->dropIndex(['assigned_by_user_id']);
                $table->dropUnique(['safe_zone_id', 'assigned_user_id']);
            } catch (\Exception $e) {
                // Index peuvent ne pas exister
            }
            
            // Supprimer les nouvelles colonnes
            $table->dropColumn([
                'assigned_user_id',
                'assigned_by_user_id',
                'is_active',
                'notify_entry',
                'notify_exit',
                'notification_settings',
                'assigned_at',
                'accepted_at',
                'last_notification_at'
            ]);
            
            // Remettre les anciennes colonnes
            $table->foreignId('contact_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('pending');
        });
    }
};
