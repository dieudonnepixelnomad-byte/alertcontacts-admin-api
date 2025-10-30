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
            // Utiliser une vérification plus robuste pour les colonnes
            $columns = Schema::getColumnListing('safe_zone_assignments');
            
            if (in_array('contact_id', $columns)) {
                try {
                    // Supprimer d'abord la contrainte de clé étrangère
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
            if (in_array('status', $columns)) {
                $table->dropColumn('status');
            }
        });
        
        // Ajouter les nouvelles colonnes
        Schema::table('safe_zone_assignments', function (Blueprint $table) {
            // Récupérer la liste des colonnes existantes
            $columns = Schema::getColumnListing('safe_zone_assignments');
            
            if (!in_array('assigned_user_id', $columns)) {
                $table->foreignId('assigned_user_id')->constrained('users')->onDelete('cascade');
            }
            
            if (!in_array('assigned_by_user_id', $columns)) {
                $table->foreignId('assigned_by_user_id')->constrained('users')->onDelete('cascade');
            }
            
            if (!in_array('is_active', $columns)) {
                $table->boolean('is_active')->default(true);
            }
            
            if (!in_array('notify_entry', $columns)) {
                $table->boolean('notify_entry')->default(true);
            }
            
            if (!in_array('notify_exit', $columns)) {
                $table->boolean('notify_exit')->default(true);
            }
            
            if (!in_array('notification_settings', $columns)) {
                $table->json('notification_settings')->nullable();
            }
            
            if (!in_array('assigned_at', $columns)) {
                $table->timestamp('assigned_at')->useCurrent();
            }
            
            if (!in_array('accepted_at', $columns)) {
                $table->timestamp('accepted_at')->nullable();
            }
            
            if (!in_array('last_notification_at', $columns)) {
                $table->timestamp('last_notification_at')->nullable();
            }
        });
        
        // Ajouter les index après avoir ajouté les colonnes
        Schema::table('safe_zone_assignments', function (Blueprint $table) {
            $columns = Schema::getColumnListing('safe_zone_assignments');
            
            if (!in_array('assigned_user_id', $columns)) {
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
            $columns = Schema::getColumnListing('safe_zone_assignments');
            
            // Supprimer les index
            try {
                $table->dropIndex(['assigned_user_id', 'is_active']);
                $table->dropIndex(['safe_zone_id', 'is_active']);
                $table->dropIndex(['assigned_by_user_id']);
                $table->dropUnique(['safe_zone_id', 'assigned_user_id']);
            } catch (\Exception $e) {
                // Index peuvent ne pas exister
            }
            
            // Supprimer les nouvelles colonnes seulement si elles existent
            $columnsToRemove = [
                'assigned_user_id',
                'assigned_by_user_id',
                'is_active',
                'notify_entry',
                'notify_exit',
                'notification_settings',
                'assigned_at',
                'accepted_at',
                'last_notification_at'
            ];
            
            $existingColumnsToRemove = array_intersect($columnsToRemove, $columns);
            if (!empty($existingColumnsToRemove)) {
                $table->dropColumn($existingColumnsToRemove);
            }
            
            // Remettre les anciennes colonnes seulement si elles n'existent pas
            if (!in_array('contact_id', $columns)) {
                $table->foreignId('contact_id')->constrained('users')->onDelete('cascade');
            }
            
            if (!in_array('status', $columns)) {
                $table->string('status')->default('pending');
            }
        });
    }
};
