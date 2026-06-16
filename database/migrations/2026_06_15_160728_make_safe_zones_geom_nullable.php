<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Drop spatial index first — MySQL requires NOT NULL for spatial indexes,
        // so we must remove it before making the column nullable (V1 = circles only).
        DB::statement('ALTER TABLE safe_zones DROP INDEX safe_zones_geom_spatialindex');
        DB::statement('ALTER TABLE safe_zones MODIFY geom POLYGON NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE safe_zones SET geom = ST_GeomFromText('POLYGON((0 0, 1 0, 1 1, 0 1, 0 0))') WHERE geom IS NULL");
        DB::statement('ALTER TABLE safe_zones MODIFY geom POLYGON NOT NULL');
        DB::statement('ALTER TABLE safe_zones ADD SPATIAL INDEX safe_zones_geom_spatialindex(geom)');
    }
};
