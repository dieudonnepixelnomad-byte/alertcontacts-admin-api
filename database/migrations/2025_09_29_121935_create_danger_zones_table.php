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
        Schema::create('danger_zones', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            
            // Coordonnées géographiques (compatible avec toutes les bases de données)
            $table->decimal('center_lat', 10, 8);
            $table->decimal('center_lng', 11, 8);
            
            $table->integer('radius_m')->default(100); // Rayon en mètres
            
            // Type de danger avec enum étendu
            $table->enum('danger_type', [
                'agression',
                'vol',
                'accident',
                'vandalisme',
                'trafic_drogue',
                'zone_sensible',
                'manifestation',
                'travaux',
                'inondation',
                'autre'
            ])->default('autre');
            
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->integer('confirmations')->default(1);
            $table->timestamp('last_report_at')->useCurrent();
            $table->foreignId('reported_by')->constrained('users')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Index
            $table->index(['is_active', 'severity']);
            $table->index('last_report_at');
            $table->index('reported_by');
            $table->index('danger_type');
            
            $table->index(['center_lat', 'center_lng']);
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