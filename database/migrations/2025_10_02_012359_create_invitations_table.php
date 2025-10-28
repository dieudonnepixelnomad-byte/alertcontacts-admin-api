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
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            
            // L'utilisateur qui envoie l'invitation
            $table->foreignId('inviter_id')
                ->constrained('users')
                ->cascadeOnDelete();
            
            // Token unique pour l'invitation
            $table->string('token', 64)->unique();
            
            // Code PIN optionnel (4 chiffres)
            $table->string('pin', 4)->nullable();
            
            // Statut de l'invitation
            $table->enum('status', ['pending', 'accepted', 'refused', 'expired'])->default('pending');
            
            // Niveau de partage par défaut suggéré
            $table->enum('default_share_level', ['realtime', 'alert_only', 'none'])->default('alert_only');
            
            // Zones suggérées (JSON array)
            $table->json('suggested_zones')->nullable();
            
            // Paramètres d'expiration
            $table->timestamp('expires_at');
            $table->integer('max_uses')->default(1);
            $table->integer('used_count')->default(0);
            
            // Métadonnées
            $table->string('inviter_name')->nullable(); // Nom affiché pour l'inviteur
            $table->text('message')->nullable(); // Message personnalisé
            
            // Horodatages
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('refused_at')->nullable();
            $table->timestamps();
            
            // Index pour les recherches fréquentes
            $table->index(['token', 'status']);
            $table->index(['inviter_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
