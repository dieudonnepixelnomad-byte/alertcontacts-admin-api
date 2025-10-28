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
        Schema::create('danger_zones', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->geometry('center'); // Coordonnées géographiques (lat, lng)
            $table->integer('radius_m'); // Rayon en mètres
            $table->enum('severity', ['low', 'med', 'high'])->default('med');
            $table->integer('confirmations')->default(1);
            $table->timestamp('last_report_at');
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Index pour les requêtes géographiques
            // Index spatial (seulement si ce n'est pas SQLite)
      if (config('database.default') !== 'sqlite') {
        $table->spatialIndex('center');
      }
            // Index pour les requêtes par gravité et activité
            $table->index(['severity', 'is_active']);
            // Index pour les requêtes par date
            $table->index('last_report_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('danger_zones');
    }
};
