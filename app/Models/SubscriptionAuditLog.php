<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionAuditLog extends Model
{
    protected $fillable = [
        'user_id', 'revenuecat_event_id', 'event_type', 'outcome', 'product_id',
        'external_subscription_id', 'previous_tier', 'resulting_tier',
        'payload_hash', 'details', 'occurred_at',
    ];

    protected $casts = ['details' => 'array', 'occurred_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
