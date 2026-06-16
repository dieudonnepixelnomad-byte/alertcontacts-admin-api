<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('danger_zones', function (Blueprint $table) {
            // reported_by must be nullable for anonymous alerts
            $table->foreignId('reported_by')->nullable()->change();

            // Add all V4 community alert types to the enum
            $table->enum('danger_type', [
                // V4 community alert types
                'accident',
                'suspect',
                'fire',
                'aggression',
                'suspicious_package',
                'other',
                // Legacy types — keep for backward compatibility
                'agression',
                'vol',
                'braquage',
                'harcelement',
                'zone_non_eclairee',
                'zone_marecageuse',
                'accident_frequent',
                'deal_drogue',
                'vandalisme',
                'zone_deserte',
                'construction_dangereuse',
                'animaux_errants',
                'manifestation',
                'inondation',
                'autre',
            ])->default('other')->change();
        });
    }

    public function down(): void
    {
        Schema::table('danger_zones', function (Blueprint $table) {
            $table->foreignId('reported_by')->nullable(false)->change();

            $table->enum('danger_type', [
                'agression', 'vol', 'braquage', 'harcelement',
                'zone_non_eclairee', 'zone_marecageuse', 'accident_frequent',
                'deal_drogue', 'vandalisme', 'zone_deserte',
                'construction_dangereuse', 'animaux_errants',
                'manifestation', 'inondation', 'autre',
            ])->default('autre')->change();
        });
    }
};
