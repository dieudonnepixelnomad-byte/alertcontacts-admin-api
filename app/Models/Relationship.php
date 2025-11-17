<?php
// app/Models/Relationship.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Relationship extends Model
{
    use HasFactory;

    protected $table = 'relationships';

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = ['contact'];

    protected $fillable = [
        'user_id',
        'contact_id',
        'status',
        'share_level',
        'can_see_me',
        'accepted_at',
        'refused_at',
    ];

    protected $casts = [
        'can_see_me' => 'boolean',
        'accepted_at' => 'datetime',
        'refused_at' => 'datetime',
    ];

    /** Relations **/

    // L'utilisateur qui "ajoute" un proche
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Le "proche" (aussi un user)
    public function contact()
    {
        return $this->belongsTo(User::class, 'contact_id');
    }

    /** Scopes utiles **/

    /**
     *
     * Récupère les relations entre deux utilisateurs acceptées
     *
     * @param mixed $q
     * @param int $a
     * @param int $b
     */
    public function scopeBetween($q, int $a, int $b)
    {
        // relation (a -> b) ou (b -> a)
        return $q->where(function ($q) use ($a, $b) {
            $q->where('user_id', $a)->where('contact_id', $b);
        })->orWhere(function ($q) use ($a, $b) {
            $q->where('user_id', $b)->where('contact_id', $a);
        });
    }

    public function scopeAccepted($q)
    {
        return $q->where('status', 'accepted');
    }
}
