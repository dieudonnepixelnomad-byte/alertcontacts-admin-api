<?php
// database/migrations/2025_01_01_120000_create_relationships_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('relationships', function (Blueprint $t) {
      $t->id();

      // L'utilisateur qui "ajoute" un proche
      $t->foreignId('user_id')
        ->constrained('users')
        ->cascadeOnDelete();

      // Le proche (également un user)
      $t->foreignId('contact_id')
        ->constrained('users')
        ->cascadeOnDelete();

      // pending / accepted / refused
      $t->enum('status', ['pending', 'accepted', 'refused'])->default('pending');

      // Niveaux de partage : realtime / alert_only / none
      $t->enum('share_level', ['realtime', 'alert_only', 'none'])->default('none');

      // Transparence / permission vue : si le "user" peut voir le "contact"
      $t->boolean('can_see_me')->default(false);

      // Horodatages consentement (optionnels)
      $t->timestamp('accepted_at')->nullable();
      $t->timestamp('refused_at')->nullable();

      $t->timestamps();

      // Unicité d'une relation user → contact
      $t->unique(['user_id', 'contact_id'], 'uq_user_contact');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('relationships');
  }
};
