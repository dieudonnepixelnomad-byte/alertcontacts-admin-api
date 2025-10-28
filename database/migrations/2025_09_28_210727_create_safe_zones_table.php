<?php
// database/migrations/2025_01_01_100000_create_safe_zones_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('safe_zones', function (Blueprint $t) {
      $t->id();

      // Propriétaire de la zone
      $t->foreignId('owner_id')
        ->constrained('users')
        ->cascadeOnDelete();

      // Métadonnées
      $t->string('name', 64);
      $t->string('icon', 32)->nullable(); // ex: home, school, park

      // Représentation géo
      // - Cercle : center (POINT) + radius_m (en mètres)
      // - Polygone : geom (POLYGON)
      // Note: Les colonnes géométriques doivent être NOT NULL pour les index spatiaux
      $t->geometry('center', subtype: 'point');
      $t->unsignedInteger('radius_m')->nullable();

      $t->geometry('geom', subtype: 'polygon');

      // Fenêtres horaires actives (optionnel, ex: {"mon_fri":{"start":"07:00","end":"19:00"}})
      $t->json('active_hours')->nullable();

      // Statut
      $t->boolean('is_active')->default(true);

      $t->timestamps();

      // Index spatiaux (seulement si les colonnes sont NOT NULL et si ce n'est pas SQLite)
      if (config('database.default') !== 'sqlite') {
        $t->spatialIndex('center');
        $t->spatialIndex('geom');
      }
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('safe_zones');
  }
};
