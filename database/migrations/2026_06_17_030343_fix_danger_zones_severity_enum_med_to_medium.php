<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("UPDATE danger_zones SET severity = 'medium' WHERE severity = 'med'");
        DB::statement("ALTER TABLE danger_zones MODIFY COLUMN severity ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium'");
    }

    public function down(): void
    {
        DB::statement("UPDATE danger_zones SET severity = 'med' WHERE severity = 'medium'");
        DB::statement("ALTER TABLE danger_zones MODIFY COLUMN severity ENUM('low', 'med', 'high') NOT NULL DEFAULT 'med'");
    }
};
