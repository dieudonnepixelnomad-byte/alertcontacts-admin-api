<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'inviter_id',
        'token',
        'pin',
        'status',
        'default_share_level',
        'suggested_zones',
        'expires_at',
        'max_uses',
        'used_count',
        'inviter_name',
        'message',
        'accepted_at',
        'refused_at',
    ];

    protected $casts = [
        'suggested_zones' => 'array',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'refused_at' => 'datetime',
    ];

    /** Relations **/

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    /** Scopes **/

    public function scopeActive($query)
    {
        return $query->where('status', 'pending')
                    ->where('expires_at', '>', now())
                    ->whereRaw('used_count < max_uses');
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
                    ->orWhereRaw('used_count >= max_uses');
    }

    /** Méthodes utilitaires **/

    public function isValid(): bool
    {
        return $this->status === 'pending' 
            && $this->expires_at > now() 
            && $this->used_count < $this->max_uses;
    }

    public function isExpired(): bool
    {
        return $this->expires_at <= now() || $this->used_count >= $this->max_uses;
    }

    public function canBeUsed(): bool
    {
        return $this->isValid();
    }

    public function markAsUsed(): void
    {
        $this->increment('used_count');
        
        if ($this->used_count >= $this->max_uses) {
            $this->update(['status' => 'expired']);
        }
    }

    public function accept(): void
    {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
        $this->markAsUsed();
    }

    public function refuse(): void
    {
        $this->update([
            'status' => 'refused',
            'refused_at' => now(),
        ]);
        $this->markAsUsed();
    }

    /** Méthodes statiques **/

    public static function generateToken(): string
    {
        do {
            $token = Str::random(32);
        } while (self::where('token', $token)->exists());

        return $token;
    }

    public static function generatePin(): string
    {
        return str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public static function createInvitation(array $data): self
    {
        $invitation = new self();
        $invitation->inviter_id = $data['inviter_id'];
        $invitation->token = self::generateToken();
        $invitation->pin = $data['require_pin'] ? self::generatePin() : null;
        $invitation->default_share_level = $data['default_share_level'] ?? 'alert_only';
        $invitation->suggested_zones = $data['suggested_zones'] ?? [];
        $invitation->expires_at = $data['expires_at'] ?? now()->addHours(24);
        $invitation->max_uses = $data['max_uses'] ?? 1;
        $invitation->inviter_name = $data['inviter_name'] ?? null;
        $invitation->message = $data['message'] ?? null;
        
        $invitation->save();
        
        return $invitation;
    }

    /** Accesseurs **/

    public function getRemainingUsesAttribute(): int
    {
        return max(0, $this->max_uses - $this->used_count);
    }

    public function getInvitationUrlAttribute(): string
    {
        $baseUrl = config('app.frontend_url', config('app.url'));
        $url = "{$baseUrl}/invitations/accept?t={$this->token}";
        
        if ($this->pin) {
            $url .= "&pin={$this->pin}";
        }
        
        return $url;
    }
}
