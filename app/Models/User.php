<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Panel;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'firebase_uid',
        'provider',
        'avatar_url',
        'phone_number',
        'email_verified_at',
        'fcm_token',
        'fcm_platform',
        'fcm_token_updated_at',
        'quiet_hours_start',
        'quiet_hours_end',
        'timezone',
        'quiet_hours_enabled',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'fcm_token_updated_at' => 'datetime',
            'password' => 'hashed',
            'quiet_hours_enabled' => 'boolean',
        ];
    }

    /**
     * Find user by Firebase UID
     */
    public static function findByFirebaseUid(string $firebaseUid): ?self
    {
        return static::where('firebase_uid', $firebaseUid)->first();
    }

    /**
     * Create or update user from Firebase data
     */
    public static function createOrUpdateFromFirebase(array $firebaseData): self
    {
        $user = static::findByFirebaseUid($firebaseData['uid']);

        $userData = [
            'name' => $firebaseData['name'] ?? $firebaseData['email'],
            'email' => $firebaseData['email'],
            'firebase_uid' => $firebaseData['uid'],
            'provider' => $firebaseData['provider'] ?? 'firebase',
            'avatar_url' => $firebaseData['picture'] ?? null,
            'phone_number' => $firebaseData['phone_number'] ?? null,
            'email_verified_at' => $firebaseData['email_verified'] ? now() : null,
        ];

        if ($user) {
            $user->update($userData);
            return $user;
        }

        return static::create($userData);
    }

    /**
     * Get the quiet hours start time formatted as HH:MM
     */
    public function getQuietHoursStartAttribute($value)
    {
        return $value ? substr($value, 0, 5) : null;
    }

    /**
     * Get the quiet hours end time formatted as HH:MM
     */
    public function getQuietHoursEndAttribute($value)
    {
        return $value ? substr($value, 0, 5) : null;
    }

    // app/Models/User.php (extraits)
    public function myContacts()
    {
        return $this->hasMany(Relationship::class, 'user_id')->where('status', 'accepted');
    }
    public function relatedToMe()
    {
        return $this->hasMany(Relationship::class, 'contact_id')->where('status', 'accepted');
    }

    // Can access Panel Filament Admin
    public function canAccessPanel(Panel $panel): bool
    {
        // Permettre l'accès aux emails spécifiques (super admins)
        if ($this->email === 'dieudonnegwet86@gmail.com' || $this->email === 'edwige.gnaly1@gmail.com') {
            return true;
        }
        
        // Permettre l'accès si l'utilisateur a un champ is_admin à true
        if (isset($this->attributes['is_admin']) && $this->attributes['is_admin']) {
            return true;
        }
        
        // Vérifier directement dans la base de données (pour éviter les problèmes de cache Hostinger)
        $user = static::where('id', $this->id)->first();
        if ($user && isset($user->is_admin) && $user->is_admin) {
            return true;
        }
        
        // Permettre l'accès temporaire UNIQUEMENT en environnement local
        if (app()->environment(['local', 'testing']) && $this->hasVerifiedEmail()) {
            return true;
        }
        
        return false;
    }
}
