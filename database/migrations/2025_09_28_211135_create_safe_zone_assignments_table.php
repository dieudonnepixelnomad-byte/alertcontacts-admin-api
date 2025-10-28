<?php
// database/migrations/2025_01_01_110000_create_safe_zone_assignments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('safe_zone_assignments', function (Blueprint $t) {
      $t->id();

      $t->foreignId('safe_zone_id')
        ->constrained('safe_zones')
        ->cascadeOnDelete();

      // L’utilisateur "proche" concerné par la géofence
      $t->foreignId('contact_id')
        ->constrained('users')
        ->cascadeOnDelete();

      $t->enum('status', ['active', 'paused'])->default('active');

      $t->timestamps();

      $t->unique(['safe_zone_id', 'contact_id'], 'uq_safezone_contact');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('safe_zone_assignments');
  }
};
