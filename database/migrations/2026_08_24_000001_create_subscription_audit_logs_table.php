<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('revenuecat_event_id')->nullable()->index();
            $table->string('event_type');
            $table->string('outcome'); // processed, duplicate, ignored, error
            $table->string('product_id')->nullable();
            $table->string('external_subscription_id')->nullable();
            $table->string('previous_tier')->nullable();
            $table->string('resulting_tier')->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['outcome', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_audit_logs');
    }
};
