# CLAUDE.md — AlertContacts Backend (Laravel)

## Identité du projet

Backend API REST pour **AlertContacts V4** — Application de sécurité familiale & tranquillité d'esprit.
Ce dépôt est **exclusivement le backend**. Il ne sert aucune vue HTML.
Le frontend Flutter est dans un dépôt séparé.

CDC de référence : `AlertContacts_CDC_V4_1.pdf`
CLAUDE.md Flutter : voir le dépôt mobile.

---

## Stack technique

| Couche                     | Technologie                                       | Version |
| -------------------------- | ------------------------------------------------- | ------- |
| Framework                  | Laravel                                           | 11.x    |
| Langage                    | PHP                                               | 8.2+    |
| Base de données principale | MySQL                                             | 8.0+    |
| Cache & Queue driver       | Redis                                             | 7.x     |
| Queue dashboard            | Laravel Horizon                                   | latest  |
| Auth tokens                | Laravel Sanctum                                   | latest  |
| Push notifications         | Firebase Cloud Messaging (FCM HTTP v1 API)        | —       |
| Paiement Europe            | Stripe                                            | —       |
| Paiement Afrique           | PayDunya                                          | —       |
| Variables d'env            | `.env` + `config/` — jamais de valeurs hardcodées | —       |

---

## Architecture — Règle fondamentale (rappel)

```
Flutter → Firebase Auth → ID Token → POST /api/auth/firebase → Sanctum Token

Flutter ←→ Laravel API  (logique métier, zones, proches, alertes, abonnements)
Flutter ←→ Firebase     (positions GPS temps réel, notifications push)

Laravel → Firebase Admin SDK  (envoi FCM uniquement — Laravel ne lit JAMAIS Firebase Realtime DB)
```

**Laravel est la source de vérité pour toute la logique métier.**
**Firebase Realtime DB est la source de vérité pour les positions GPS en temps réel.**
Laravel ne communique avec Firebase que dans un seul sens : envoyer des notifications FCM.

---

## Structure du projet

```
app/
├── Console/
│   └── Commands/           # Commandes Artisan custom (purge alertes expirées, etc.)
├── Exceptions/
│   └── Handler.php         # Formatage JSON uniforme des erreurs
├── Http/
│   ├── Controllers/
│   │   └── Api/            # TOUS les controllers sont dans Api/ — pas à la racine
│   │       ├── AuthController.php
│   │       ├── LocationController.php
│   │       ├── ContactController.php
│   │       ├── InvitationController.php
│   │       ├── ZoneController.php
│   │       ├── AlertController.php
│   │       ├── SubscriptionController.php
│   │       └── AppVersionController.php
│   ├── Middleware/
│   │   ├── CheckSubscriptionTier.php   # Vérifie le tier pour les routes Premium
│   │   └── RateLimitLocation.php      # Rate limit custom POST /api/location
│   ├── Requests/                       # Un FormRequest par action — JAMAIS de validation dans le controller
│   │   ├── Auth/
│   │   ├── Location/
│   │   ├── Zone/
│   │   ├── Alert/
│   │   └── Invitation/
│   └── Resources/                      # Un ApiResource par modèle exposé
│       ├── UserResource.php
│       ├── ContactResource.php
│       ├── ZoneResource.php
│       ├── AlertResource.php
│       └── SubscriptionResource.php
├── Jobs/                               # Tous les jobs asynchrones
│   ├── NotifyZoneEntry.php
│   ├── NotifyZoneExit.php
│   ├── NotifyNearbyUsers.php
│   ├── SendFcmNotification.php
│   └── PurgeExpiredAlerts.php
├── Models/
│   ├── User.php
│   ├── Contact.php
│   ├── Invitation.php
│   ├── Zone.php
│   ├── UserZoneState.php
│   ├── CommunityAlert.php
│   ├── AlertView.php
│   └── Subscription.php
├── Notifications/                      # Optionnel — si on utilise le système Notifiable de Laravel
├── Policies/                           # Autorisation par modèle
│   ├── ZonePolicy.php
│   ├── ContactPolicy.php
│   └── AlertPolicy.php
├── Services/                           # Logique métier — pas dans les controllers
│   ├── FirebaseService.php             # Envoi FCM uniquement
│   ├── ZoneDetectionService.php        # Haversine + user_zone_states
│   ├── InvitationService.php
│   ├── SubscriptionService.php
│   └── AlertProximityService.php
└── Traits/
    └── ApiResponse.php                 # Format de réponse JSON uniforme

database/
├── migrations/
└── seeders/

routes/
└── api.php                             # Seul fichier de routes — pas de web.php utilisé

config/
├── firebase.php                        # Config Firebase Admin SDK
├── paydunya.php                        # Config PayDunya
└── alertcontacts.php                   # Constantes métier (limites tiers, durées alertes, etc.)
```

---

## Règles de code — Non négociables

### 1. Controllers — fins et sans logique métier

```php
// ✅ Correct
class LocationController extends Controller
{
    public function __construct(private ZoneDetectionService $zoneService) {}

    public function update(UpdateLocationRequest $request): JsonResponse
    {
        $result = $this->zoneService->processLocation(
            auth()->user(),
            $request->validated()
        );
        return $this->success($result);
    }
}

// ❌ Interdit — logique métier dans le controller
class LocationController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $lat = $request->lat;
        $lng = $request->lng;
        foreach (auth()->user()->zones as $zone) {
            // ... 50 lignes de logique Haversine ici
        }
    }
}
```

**Règle** : un controller method ne doit pas dépasser 15 lignes. Tout ce qui dépasse va dans un Service.

### 2. Validation — toujours dans un FormRequest

```php
// Un FormRequest par action, jamais $request->validate() dans le controller
class CreateZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:30',
            'lat'         => 'required|numeric|between:-90,90',
            'lng'         => 'required|numeric|between:-180,180',
            'radius'      => 'required|integer|between:50,500',
            'icon'        => 'nullable|in:home,school,work,sport,shopping,other',
            'color'       => 'nullable|in:teal,orange,pink,green,purple',
            'contact_ids' => 'nullable|array',
            'contact_ids.*' => 'exists:contacts,id',
        ];
    }
}
```

### 3. Réponses API — format uniforme via Trait

```php
// app/Traits/ApiResponse.php
trait ApiResponse
{
    protected function success(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['status' => 'ok', 'data' => $data], $status);
    }

    protected function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => $message,
            'errors'  => $errors,
        ], $status);
    }
}

// Format de réponse POST /api/location — spécifique au CDC
protected function locationResponse(bool $alertsNearby, int $nextInterval): JsonResponse
{
    return response()->json([
        'status'               => 'ok',
        'alerts_nearby'        => $alertsNearby,
        'next_update_interval' => $nextInterval,
    ]);
}
```

### 4. Eloquent — jamais de raw SQL sauf géospatial

```php
// ✅ Éloquent pour tout
$zones = auth()->user()->zones()->with('contacts')->get();

// ✅ Exception autorisée : requêtes géospatiales MySQL 8
$nearbyAlerts = DB::select("
    SELECT * FROM community_alerts
    WHERE active = 1
    AND expires_at > NOW()
    AND ST_Distance_Sphere(
        POINT(lng, lat),
        POINT(?, ?)
    ) <= radius
    AND id NOT IN (SELECT alert_id FROM alert_views WHERE user_id = ?)
", [$userLng, $userLat, $userId]);

// ❌ Interdit — raw SQL pour des opérations CRUD simples
DB::statement("UPDATE users SET tier = 'solo' WHERE id = ?", [$userId]);
```

### 5. Jobs — toujours asynchrones pour FCM

```php
// ✅ Correct — dispatché en queue
NotifyZoneEntry::dispatch($user, $zone)->onQueue('notifications');

// ❌ Interdit — synchrone dans le cycle HTTP
(new NotifyZoneEntry($user, $zone))->handle();
```

---

## Authentification — Flux complet Firebase → Sanctum

```php
// AuthController@firebase
public function firebase(Request $request): JsonResponse
{
    $request->validate(['firebase_token' => 'required|string']);

    // 1. Vérifier le token Firebase via Firebase Admin SDK
    $firebaseUser = $this->firebaseService->verifyIdToken($request->firebase_token);

    if (!$firebaseUser) {
        return $this->error('Token Firebase invalide', 401);
    }

    // 2. Créer ou mettre à jour l'utilisateur en base
    $user = User::updateOrCreate(
        ['firebase_uid' => $firebaseUser->uid],
        [
            'email'        => $firebaseUser->email,
            'display_name' => $firebaseUser->displayName,
            'avatar_url'   => $firebaseUser->photoURL,
        ]
    );

    // 3. Révoquer tous les anciens tokens + créer un nouveau token Sanctum
    $user->tokens()->delete();
    $token = $user->createToken('flutter-app')->plainTextToken;

    return $this->success([
        'token' => $token,
        'user'  => new UserResource($user),
    ], 201);
}
```

**FirebaseService** — vérification du token :

```php
// app/Services/FirebaseService.php
class FirebaseService
{
    private Auth $auth;

    public function __construct()
    {
        $this->auth = app('firebase.auth'); // Kreait Firebase Admin SDK
    }

    public function verifyIdToken(string $idToken): ?UserRecord
    {
        try {
            $verifiedToken = $this->auth->verifyIdToken($idToken);
            return $this->auth->getUser($verifiedToken->claims()->get('sub'));
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function sendNotification(string $fcmToken, array $notification, array $data = []): void
    {
        // Envoi via FCM HTTP v1 API
        // Toujours dispatchée depuis un Job — jamais appelée directement
    }
}
```

**Package recommandé** : `kreait/laravel-firebase` — wrapper Laravel officiel du SDK Firebase Admin PHP.

---

## Routes — routes/api.php

```php
<?php
// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController, LocationController, ContactController,
    InvitationController, ZoneController, AlertController,
    SubscriptionController, AppVersionController
};

// --- Public ---
Route::post('/auth/firebase', [AuthController::class, 'firebase']);
Route::post('/invitations/reject', [InvitationController::class, 'reject']);
Route::get('/app/version', [AppVersionController::class, 'index']);

// --- Authentifié ---
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'update']);

    // Géolocalisation — rate limité à 1 req/min
    Route::middleware('throttle:location')->group(function () {
        Route::post('/location', [LocationController::class, 'update']);
    });
    Route::post('/location/pause', [LocationController::class, 'pause']);
    Route::post('/location/resume', [LocationController::class, 'resume']);

    // Proches & Invitations
    Route::get('/contacts', [ContactController::class, 'index']);
    Route::delete('/contacts/{contact}', [ContactController::class, 'destroy']);
    Route::put('/contacts/{contact}/permissions', [ContactController::class, 'permissions']);
    Route::post('/invitations', [InvitationController::class, 'store']);
    Route::post('/invitations/accept', [InvitationController::class, 'accept']);

    // Zones
    Route::apiResource('zones', ZoneController::class);
    Route::get('/zones/{zone}/status', [ZoneController::class, 'status']);

    // Alertes communautaires
    Route::get('/alerts/nearby', [AlertController::class, 'nearby']);
    Route::post('/alerts/{alert}/confirm', [AlertController::class, 'confirm']);
    Route::post('/alerts/{alert}/deny', [AlertController::class, 'deny']);
    Route::post('/alerts/{alert}/report', [AlertController::class, 'report']);

    // Alertes communautaires — création (Premium uniquement)
    Route::middleware('tier:solo,famille')->group(function () {
        Route::post('/alerts', [AlertController::class, 'store']);
    });

    // Abonnements
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);

    // Pack Famille (Famille uniquement)
    Route::middleware('tier:famille')->group(function () {
        Route::post('/subscriptions/family/invite', [SubscriptionController::class, 'familyInvite']);
        Route::delete('/subscriptions/family/{member}', [SubscriptionController::class, 'familyRemove']);
    });
});
```

---

## Migrations — tables critiques

### users

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('firebase_uid')->unique();
    $table->string('email')->unique()->nullable();
    $table->string('display_name')->nullable();
    $table->string('avatar_url')->nullable();
    $table->string('fcm_token')->nullable();              // Mis à jour par Flutter à chaque lancement
    $table->enum('tier', ['free', 'solo', 'famille'])->default('free');
    $table->enum('personalization_profile', ['children', 'parents', 'partner', 'self'])->nullable();
    $table->boolean('location_paused')->default(false);
    $table->timestamp('location_paused_until')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

### contacts (relation bidirectionnelle)

```php
Schema::create('contacts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('contact_user_id')->constrained('users')->cascadeOnDelete();
    // Permissions de partage
    $table->boolean('share_location')->default(true);
    $table->boolean('share_zone_alerts')->default(true);
    $table->boolean('share_battery')->default(true);
    $table->timestamps();

    // Un user ne peut avoir le même contact deux fois
    $table->unique(['user_id', 'contact_user_id']);
});
```

### invitations

```php
Schema::create('invitations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
    $table->string('token')->unique();              // Token du deep link Firebase Dynamic Links
    $table->string('invitee_email')->nullable();    // Optionnel — si connu
    $table->foreignId('invitee_id')->nullable()->constrained('users')->nullOnDelete();
    $table->enum('profile_target', ['children', 'parents', 'partner', 'self']);
    $table->text('message')->nullable();
    $table->enum('status', ['pending', 'accepted', 'rejected', 'expired'])->default('pending');
    $table->timestamp('expires_at');                // +7 jours par défaut
    $table->timestamps();
});
```

### zones

```php
Schema::create('zones', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('name', 30);
    $table->decimal('lat', 10, 7);
    $table->decimal('lng', 10, 7);
    $table->unsignedSmallInteger('radius')->default(150); // en mètres, 50-500
    $table->enum('icon', ['home', 'school', 'work', 'sport', 'shopping', 'other'])->default('other');
    $table->string('color', 20)->default('teal');
    $table->timestamps();

    // Index géospatial — obligatoire pour les performances
    $table->index(['lat', 'lng']);
});

// Table pivot zones <-> contacts
Schema::create('contact_zone', function (Blueprint $table) {
    $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
    $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
    $table->primary(['zone_id', 'contact_id']);
});
```

### user_zone_states (table critique — évite les doublons de notifications)

```php
Schema::create('user_zone_states', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
    $table->enum('state', ['inside', 'outside'])->default('outside');
    $table->timestamp('entered_at')->nullable();
    $table->timestamp('exited_at')->nullable();
    $table->timestamps();

    $table->unique(['user_id', 'zone_id']); // Un état par couple user/zone
});
```

### community_alerts

```php
Schema::create('community_alerts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // null si anonyme
    $table->enum('type', ['accident', 'suspect', 'fire', 'aggression', 'suspicious_package', 'other']);
    $table->enum('gravity', ['low', 'medium', 'high']);
    $table->decimal('lat', 10, 7);
    $table->decimal('lng', 10, 7);
    $table->unsignedSmallInteger('radius');         // Rayon de diffusion en mètres (200/500/1000)
    $table->text('description')->nullable();
    $table->enum('visibility', ['public', 'contacts'])->default('public');
    $table->boolean('anonymous')->default(true);
    $table->boolean('active')->default(true);
    $table->unsignedSmallInteger('confirmations_count')->default(0);
    $table->timestamp('expires_at');                // +30min/1h/2h selon gravité
    $table->timestamps();

    // Index géospatial — utilisé par ST_Distance_Sphere
    $table->index(['lat', 'lng', 'active']);
});
```

### subscriptions

```php
Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('family_owner_id')->nullable()->constrained('users')->nullOnDelete();
    $table->enum('tier', ['solo', 'famille']);
    $table->enum('billing_cycle', ['monthly', 'annual']);
    $table->string('payment_provider');             // stripe | paydunya
    $table->string('external_subscription_id');     // ID chez Stripe ou PayDunya
    $table->boolean('trial_active')->default(false);
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('current_period_ends_at');
    $table->enum('status', ['active', 'trialing', 'cancelled', 'expired'])->default('active');
    $table->timestamps();
});
```

---

## ZoneDetectionService — logique métier centrale

```php
// app/Services/ZoneDetectionService.php
class ZoneDetectionService
{
    public function processLocation(User $user, array $locationData): array
    {
        ['lat' => $lat, 'lng' => $lng] = $locationData;

        // 1. Zones en cache Redis (TTL 5 min) — évite MySQL à chaque position
        $zones = Cache::remember(
            "user_zones_{$user->id}",
            300,
            fn() => $user->zones()->with('contacts')->get()
        );

        // 2. Détection d'entrée/sortie pour chaque zone
        foreach ($zones as $zone) {
            $this->checkZoneTransition($user, $zone, $lat, $lng);
        }

        // 3. Alertes communautaires à proximité
        $alertsNearby = $this->hasAlertsNearby($user->id, $lat, $lng);

        // 4. Calcul de l'intervalle suivant
        $nextInterval = $this->computeNextInterval($user, $alertsNearby);

        return [
            'alerts_nearby'        => $alertsNearby,
            'next_update_interval' => $nextInterval,
        ];
    }

    private function checkZoneTransition(User $user, Zone $zone, float $lat, float $lng): void
    {
        $isInside = $zone->containsPoint($lat, $lng);

        $state = UserZoneState::firstOrCreate(
            ['user_id' => $user->id, 'zone_id' => $zone->id],
            ['state' => 'outside']
        );

        if ($isInside && $state->state === 'outside') {
            $state->update(['state' => 'inside', 'entered_at' => now()]);
            NotifyZoneEntry::dispatch($user, $zone)->onQueue('notifications');

        } elseif (!$isInside && $state->state === 'inside') {
            $state->update(['state' => 'outside', 'exited_at' => now()]);
            NotifyZoneExit::dispatch($user, $zone)->onQueue('notifications');
        }
    }

    private function hasAlertsNearby(int $userId, float $lat, float $lng): bool
    {
        return DB::table('community_alerts')
            ->whereRaw("
                active = 1
                AND expires_at > NOW()
                AND ST_Distance_Sphere(POINT(lng, lat), POINT(?, ?)) <= radius
                AND id NOT IN (SELECT alert_id FROM alert_views WHERE user_id = ?)
            ", [$lng, $lat, $userId])
            ->exists();
    }

    private function computeNextInterval(User $user, bool $alertsNearby): int
    {
        // Alerte active dans le rayon → mise à jour toutes les 60s
        if ($alertsNearby) return 60;
        // Par défaut → 5 minutes
        return 300;
    }
}
```

---

## Policies — Autorisation par modèle

```php
// app/Policies/ZonePolicy.php
class ZonePolicy
{
    // Un user ne peut voir/modifier/supprimer que SES zones
    public function view(User $user, Zone $zone): bool    { return $user->id === $zone->user_id; }
    public function update(User $user, Zone $zone): bool  { return $user->id === $zone->user_id; }
    public function delete(User $user, Zone $zone): bool  { return $user->id === $zone->user_id; }

    // Vérification de la limite du tier gratuit AVANT création
    public function create(User $user): bool
    {
        if ($user->tier === 'free') {
            $limit = config('alertcontacts.free_tier.zones_limit'); // 1
            return $user->zones()->count() < $limit;
        }
        return true;
    }
}

// app/Policies/ContactPolicy.php
class ContactPolicy
{
    public function create(User $user): bool
    {
        if ($user->tier === 'free') {
            $limit = config('alertcontacts.free_tier.contacts_limit'); // 2
            return $user->contacts()->count() < $limit;
        }
        return true;
    }
}
```

---

## Middleware — CheckSubscriptionTier

```php
// app/Http/Middleware/CheckSubscriptionTier.php
class CheckSubscriptionTier
{
    public function handle(Request $request, Closure $next, string ...$tiers): Response
    {
        $user = $request->user();

        if (!in_array($user->tier, $tiers)) {
            return response()->json([
                'status'          => 'error',
                'message'         => 'Abonnement requis',
                'required_tiers'  => $tiers,
                'current_tier'    => $user->tier,
                'upgrade_url'     => '/api/subscriptions',  // Flutter affiche le paywall
            ], 403);
        }

        return $next($request);
    }
}
```

---

## Queue — Configuration et priorités

### config/queue.php

```php
'redis' => [
    'driver'      => 'redis',
    'connection'  => 'default',
    'queue'       => env('REDIS_QUEUE', 'default'),
    'retry_after' => 90,
    'block_for'   => 5,
],
```

### Queues nommées — ordre de priorité décroissante

| Queue           | Contenu                                                         | Priorité |
| --------------- | --------------------------------------------------------------- | -------- |
| `notifications` | FCM zone entry/exit, alertes communautaires proches             | Haute    |
| `alerts`        | Diffusion alertes communautaires publiques (ST_Distance_Sphere) | Haute    |
| `invitations`   | Deep links invitations                                          | Normale  |
| `subscriptions` | Webhooks Stripe/PayDunya                                        | Normale  |
| `cleanup`       | Purge alertes expirées, tokens expirés                          | Basse    |

```bash
# Supervisor — lancer les workers avec priorité
php artisan queue:work redis --queue=notifications,alerts,invitations,subscriptions,cleanup --tries=3 --max-time=3600
```

### Laravel Horizon — horizon.php

```php
'environments' => [
    'production' => [
        'supervisor-notifications' => [
            'queue'      => ['notifications', 'alerts'],
            'processes'  => 4,           // 4 workers pour les notifs critiques
            'tries'      => 3,
            'timeout'    => 30,
        ],
        'supervisor-default' => [
            'queue'      => ['invitations', 'subscriptions', 'cleanup'],
            'processes'  => 2,
            'tries'      => 3,
            'timeout'    => 60,
        ],
    ],
],
```

---

## Rate Limiting — config/app.php & bootstrap/app.php

```php
// bootstrap/app.php — Laravel 11
->withMiddleware(function (Middleware $middleware) {

    // Rate limit custom pour POST /api/location : 1 req/min par user
    RateLimiter::for('location', function (Request $request) {
        return Limit::perMinute(1)->by($request->user()?->id ?: $request->ip());
    });

    // Rate limit standard API : 60 req/min par user
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });
})
```

---

## Cache Redis — Clés et TTL

| Clé                         | Contenu                                    | TTL   | Invalidation                                |
| --------------------------- | ------------------------------------------ | ----- | ------------------------------------------- |
| `user_zones_{id}`           | Zones de l'utilisateur + contacts associés | 300s  | À chaque création/modif/suppression de zone |
| `user_tier_{id}`            | Tier de l'abonnement actif                 | 3600s | À chaque changement d'abonnement            |
| `nearby_alerts_{lat}_{lng}` | Alertes communautaires proches             | 60s   | Automatique par TTL                         |
| `app_version`               | Version min/courante de l'app              | 3600s | Manuel depuis la console                    |

```php
// Invalider le cache zones après toute modification
Cache::forget("user_zones_{$user->id}");

// Invalider depuis la Policy après création de zone
// → Toujours invalider APRÈS le succès de l'opération, jamais avant
```

---

## config/alertcontacts.php — constantes métier

```php
<?php
// config/alertcontacts.php
// Toutes les constantes métier du CDC en un seul endroit
// Jamais hardcodées dans le code

return [

    'free_tier' => [
        'contacts_limit' => env('FREE_CONTACTS_LIMIT', 2),
        'zones_limit'    => env('FREE_ZONES_LIMIT', 1),
        'alert_history_hours' => 24,
    ],

    'trial' => [
        'duration_days' => 7,
    ],

    'alerts' => [
        'gravity' => [
            'low'    => ['duration_minutes' => 30,  'radius_meters' => 200],
            'medium' => ['duration_minutes' => 60,  'radius_meters' => 500],
            'high'   => ['duration_minutes' => 120, 'radius_meters' => 1000],
        ],
        'confirmations_to_validate' => 2,
    ],

    'zones' => [
        'radius_min'     => 50,
        'radius_max'     => 500,
        'radius_default' => 150,
    ],

    'location' => [
        'update_interval_foreground'  => 10,   // secondes
        'update_interval_background'  => 300,  // secondes
        'update_interval_alert_nearby'=> 60,   // secondes
        'update_interval_idle'        => 900,  // secondes (15 min immobile)
    ],

    'invisible_mode' => [
        'duration_options' => [60, 240, 0], // 0 = jusqu'à réactivation manuelle
    ],

    'invitations' => [
        'expiry_days' => 7,
    ],

    'prices' => [
        'solo'   => ['monthly' => 4.99,  'annual' => 34.99],
        'family' => ['monthly' => 8.99,  'annual' => 59.99],
        'family_max_members' => 6,
    ],

];
```

---

## Bonnes pratiques de sécurité

| Règle                   | Détail                                                                           |
| ----------------------- | -------------------------------------------------------------------------------- |
| Sanctum uniquement      | Pas de JWT, pas de Passport — Sanctum suffit pour une app mobile                 |
| `$request->validated()` | Toujours utiliser `.validated()` — jamais `.all()` ou `.input()` sans validation |
| Policy sur chaque route | `$this->authorize('create', Zone::class)` avant toute opération sensible         |
| Soft deletes            | Sur `users` et `zones` — jamais de suppression physique en production            |
| Logs Laravel            | Ne jamais logger de tokens, de coordonnées GPS précises, ou d'emails             |
| CORS                    | Restreindre aux origines autorisées — pas de `*` en production                   |
| SQL Injection           | Toujours des bindings `?` dans les raw queries — pas de concaténation de chaînes |

---

## Commandes Artisan custom

```bash
# Purger les alertes communautaires expirées (à scheduler toutes les 30 min)
php artisan alerts:purge-expired

# Purger les invitations expirées (quotidien)
php artisan invitations:purge-expired

# Vérifier les abonnements expirés et downgrader les users (horaire)
php artisan subscriptions:check-expired
```

### Scheduler — app/Console/Kernel.php

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('alerts:purge-expired')->everyThirtyMinutes();
    $schedule->command('invitations:purge-expired')->daily();
    $schedule->command('subscriptions:check-expired')->hourly();
}
```

---

## Codes de réponse HTTP — Convention

| Code  | Usage                                                           |
| ----- | --------------------------------------------------------------- |
| `200` | GET réussi, PUT réussi                                          |
| `201` | POST réussi (ressource créée)                                   |
| `204` | DELETE réussi (pas de body)                                     |
| `400` | Requête invalide (validation échouée)                           |
| `401` | Token manquant ou invalide                                      |
| `403` | Authentifié mais non autorisé (tier insuffisant, mauvais owner) |
| `404` | Ressource non trouvée                                           |
| `422` | Erreur de validation FormRequest (Laravel défaut)               |
| `429` | Rate limit atteint                                              |
| `500` | Erreur serveur non gérée                                        |

---

## Variables d'environnement — .env requis

```env
APP_NAME=AlertContacts
APP_ENV=production
APP_KEY=
APP_URL=https://api.alertcontacts.app

DB_CONNECTION=mysql
DB_HOST=
DB_PORT=3306
DB_DATABASE=alertcontacts
DB_USERNAME=
DB_PASSWORD=

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=
REDIS_PASSWORD=
REDIS_PORT=6379

FIREBASE_CREDENTIALS=/path/to/firebase-service-account.json
FIREBASE_PROJECT_ID=

STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=

PAYDUNYA_MASTER_KEY=
PAYDUNYA_PRIVATE_KEY=
PAYDUNYA_PUBLIC_KEY=
PAYDUNYA_TOKEN=
PAYDUNYA_MODE=live

FREE_CONTACTS_LIMIT=2
FREE_ZONES_LIMIT=1
```

---

## Décisions figées — Ne pas remettre en question

| Décision                    | Choix                                        | Raison                                             |
| --------------------------- | -------------------------------------------- | -------------------------------------------------- |
| Auth                        | Firebase → Sanctum (pas JWT, pas Passport)   | Passwordless imposé par le CDC                     |
| Queue driver                | Redis (pas database)                         | Performance — notifications critiques temps réel   |
| Géospatial                  | ST_Distance_Sphere MySQL 8 (pas PostGIS)     | MySQL déjà dans le stack                           |
| Cache zones                 | Redis TTL 5min (pas query à chaque position) | POST /api/location peut être appelé toutes les 10s |
| Notifications FCM           | Toujours via Queue — jamais synchrone        | Le cycle HTTP ne doit jamais attendre FCM          |
| Laravel ne lit pas Firebase | Flutter = seul pont                          | Architecture CDC — ne pas contourner               |
| Soft deletes users          | Oui                                          | RGPD — droit à la suppression différée             |
