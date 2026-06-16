<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tier', ['solo', 'famille']);
            $table->enum('billing_cycle', ['monthly', 'annual']);
            $table->string('payment_provider');
            $table->string('external_subscription_id')->nullable();
            $table->boolean('trial_active')->default(false);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_ends_at');
            $table->enum('status', ['active', 'trialing', 'cancelled', 'expired'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
