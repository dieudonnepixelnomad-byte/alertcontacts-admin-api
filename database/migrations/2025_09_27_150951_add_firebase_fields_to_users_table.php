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
        Schema::table('users', function (Blueprint $table) {
            $table->string('firebase_uid')->nullable()->unique()->after('id');
            $table->string('provider')->default('email')->after('firebase_uid');
            $table->string('avatar_url')->nullable()->after('email');
            $table->string('phone_number')->nullable()->after('avatar_url');
            
            // Modifier la colonne password pour qu'elle soit nullable (pour les utilisateurs Google)
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'firebase_uid',
                'provider',
                'avatar_url',
                'phone_number'
            ]);
            
            // Remettre password comme requis
            $table->string('password')->nullable(false)->change();
        });
    }
};
