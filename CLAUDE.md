# CLAUDE.md — AlertContacts Admin (Backend Laravel)

## Contexte du projet

**AlertContacts Admin** est le backend REST API de l'application mobile AlertContacts V3.
- **Framework** : Laravel 12, PHP ^8.2
- **Base de données** : PostgreSQL + PostGIS (géospatial)
- **Authentification API** : Firebase Auth + Laravel Sanctum
- **Push notifications** : Firebase Cloud Messaging (FCM) via `kreait/firebase-php`
- **Admin panel** : Filament 4.0
- **File d'attente** : Queue Laravel (driver database)
- **Tests** : Pest PHP

---

## Architecture générale

```
Routes (api.php / web.php)
  └── Controllers (Http/Controllers/Api/)
        └── Services (Services/)
              ├── NotificationService → FirebaseNotificationService
              ├── GeoprocessingService → CooldownService
              ├── QuietHoursService
              └── ActivityLogService
```

Les contrôleurs ne contiennent pas de logique métier — tout passe par les services.  
Les Jobs gèrent le traitement asynchrone (localisation, notifications, exports).

---

## Authentification

Toutes les routes API protégées utilisent le middleware `auth:sanctum`.  
L'authentification se fait via Firebase ID Token → `POST /api/auth/firebase-login` → token Sanctum.  
Le modèle `User` a un champ `firebase_uid` pour le mapping Firebase ↔ Sanctum.

```
POST /api/auth/firebase-login   ← login Firebase (public)
POST /api/auth/logout           ← déconnexion (auth:sanctum)
GET  /api/me                    ← profil courant (auth:sanctum)
```

---

## Modules et routes clés

### SafeZones (Zones de sécurité)

```
GET    /api/safe-zones                          ← lister les zones de l'utilisateur
POST   /api/safe-zones                          ← créer une zone (circulaire ou polygone)
PUT    /api/safe-zones/{safeZone}               ← modifier
DELETE /api/safe-zones/{safeZone}               ← supprimer
GET    /api/safe-zones/my-assignments           ← zones assignées par des proches
POST   /api/safe-zones/{safeZone}/assign        ← assigner des proches à une zone
PUT    /api/safe-zones/{safeZone}/notification-settings ← préférences entrée/sortie
```

**SafeZone** supporte deux géométries :
- **Circulaire** : champ `center` (Point PostGIS) + `radius_m`
- **Polygone** : champ `geom` (Polygon PostGIS)

Utilise le package `matanyadaev/laravel-eloquent-spatial` pour les requêtes spatiales.

### DangerZones (Zones de danger communautaires)

```
GET    /api/danger-zones                        ← lister (filtres: lat, lng, radius_km, min_severity, max_age_days)
POST   /api/danger-zones                        ← signaler une zone
GET    /api/danger-zones/{dangerZone}           ← détail
PUT    /api/danger-zones/{dangerZone}           ← modifier
DELETE /api/danger-zones/{dangerZone}           ← supprimer
POST   /api/danger-zones/{dangerZone}/confirm   ← confirmer la dangerosité
POST   /api/danger-zones/{dangerZone}/report-abuse ← signaler un abus
POST   /api/danger-zones/check-duplicates       ← vérifier les doublons à proximité
```

**DangerZone** utilise une géométrie circulaire simple (`center_lat`, `center_lng`, `radius_m`).  
Distances calculées via formule de Haversine (pas PostGIS pour les danger zones).

### Localisation GPS

```
POST /api/locations/batch   ← ingestion de positions GPS (queue: geoprocessing)
GET  /api/locations/recent  ← positions récentes (debug/admin)
```

Le traitement est asynchrone via le job `ProcessLocationBatch`.  
`GeoprocessingService` détecte les entrées/sorties de SafeZones et DangerZones, applique les cooldowns, et déclenche les notifications.

### Invitations et Relations

```
POST   /api/invitations           ← créer une invitation (token + PIN optionnel)
GET    /api/invitations           ← lister
POST   /api/invitations/check     ← vérifier la validité d'un token
POST   /api/invitations/accept    ← accepter et créer la relation
DELETE /api/invitations/{id}      ← supprimer

GET    /api/relationships                              ← lister les proches
GET    /api/relationships/stats                        ← statistiques
PUT    /api/relationships/{id}/share-level             ← modifier le niveau de partage
DELETE /api/relationships/{id}                         ← supprimer la relation
GET    /api/relationships/contact/{contactId}/locations ← positions GPS du proche

POST   /api/proches/{contact_id}/zones/{zone_id}   ← assigner une zone à un proche
DELETE /api/proches/{contact_id}/zones/{zone_id}   ← désassigner
PATCH  /api/proches/{contact_id}/zones/{zone_id}   ← toggle actif/inactif
```

### Quiet Hours (Heures silencieuses)

```
GET /api/quiet-hours                    ← paramètres
PUT /api/quiet-hours                    ← modifier
GET /api/quiet-hours/next-allowed-time  ← prochain créneau autorisé
GET /api/quiet-hours/timezones          ← liste des timezones
```

`QuietHoursService` est intégré dans `NotificationService` — une notification n'est jamais envoyée pendant les heures silencieuses.

### Alertes en attente

```
GET  /api/alerts/pending    ← alertes de sortie de zone en attente de confirmation
POST /api/alerts/confirm    ← confirmer la sortie de zone
POST /api/alerts/stop       ← arrêter les notifications pour une zone
```

### Activités & Feedback

```
GET /api/activities        ← journal paginé
GET /api/activities/stats  ← statistiques sur 30 jours

POST /api/feedback         ← soumettre un retour
GET  /api/feedback/types   ← types disponibles (bug/feature/compliment/complaint/other)
```

---

## Modèles clés

| Modèle | Table | Relations principales |
|--------|-------|-----------------------|
| `User` | `users` | myContacts(), relatedToMe() |
| `SafeZone` | `safe_zones` | owner(), assignments(), contacts() |
| `DangerZone` | `danger_zones` | reporter(), confirmations(), reports() |
| `Relationship` | `relationships` | user(), contact() |
| `Invitation` | `invitations` | inviter() |
| `SafeZoneEvent` | `safe_zone_events` | user(), safeZone() |
| `UserLocation` | `user_locations` | user() |
| `UserActivity` | `user_activities` | user() |
| `Cooldown` | `cooldowns` | — |
| `IgnoredDangerZone` | `ignored_danger_zones` | — |
| `PendingSafeZoneAlert` | `pending_safe_zone_alerts` | — |
| `Feedback` | `feedback` | user() |
| `AppSetting` | `app_settings` | — (clé/valeur) |

---

## Services

| Service | Responsabilité |
|---------|---------------|
| `GeoprocessingService` | Détection entrée/sortie SafeZones et DangerZones à partir d'une position GPS |
| `NotificationService` | Orchestration des envois (respect cooldown + quiet hours) |
| `FirebaseNotificationService` | Envoi FCM via Firebase Admin SDK |
| `CooldownService` | Contrôle du taux d'envoi (base de données) |
| `QuietHoursService` | Calcul timezone-aware des heures silencieuses |
| `ActivityLogService` | Journalisation des actions utilisateur |
| `IgnoredDangerZoneService` | Gestion des zones de danger ignorées |

---

## Jobs (Queue)

| Job | Queue | Description |
|-----|-------|-------------|
| `ProcessLocationBatch` | `geoprocessing` | Traitement asynchrone des positions GPS |
| `ExportUserDataJob` | default | Export RGPD des données utilisateur |
| `SendInvitationResponseNotificationJob` | default | Notification acceptation/refus invitation |
| `SendSafeZoneExitReminders` | default | Rappels de sorties de zones |

---

## Commands Artisan

| Commande | Description |
|----------|-------------|
| `app:create-admin-user` | Créer un compte administrateur |
| `app:cleanup-old-data` | Purger les données expirées (politique de rétention) |
| `app:manage-cooldowns` | Nettoyage des cooldowns expirés |
| `app:send-safe-zone-reminders` | Envoyer les rappels de zones de sécurité |
| `app:restart-workers` | Redémarrer les workers de file d'attente |

---

## Admin Panel (Filament)

Panel disponible à `/admin`. Accessible uniquement aux utilisateurs avec `is_admin = true`.  
Resources disponibles : AppSettings, DangerZones, SafeZones, Relationships, Feedback, Invitations, SafeZoneEvents.

---

## Schéma base de données (points clés)

- **PostgreSQL + PostGIS** : champs `center` (Point) et `geom` (Polygon) dans `safe_zones`
- **Cooldowns** : stockés en base (table `cooldowns`), pas Redis
- **Enums PostgreSQL** : `severity` (low/med/high), `danger_type` (15 types), `status` des relations et invitations
- **Indexation géospatiale** : index GIST sur les colonnes spatiales

---

## Contraintes et règles métier

| Règle | Détail |
|-------|--------|
| Cooldown alertes | 12h par défaut côté GeoprocessingService (configurable) |
| Quiet Hours | Aucune notification envoyée pendant les heures silencieuses |
| Invitations | Expiration 7 jours, maxUses configurable, PIN optionnel |
| Suppression compte | RGPD : suppression complète avec export disponible |
| Niveaux de partage | `realtime` / `alert_only` / `none` — géré dans `Relationship.share_level` |
| Zone dupliquée | Vérification côté backend avant création d'une DangerZone |
| Zones de sécurité | SafeZone supporte circulaire ET polygone (contrairement à DangerZone, circulaire uniquement) |

---

## Variables d'environnement critiques

```env
# Base de données
DB_CONNECTION=pgsql
DB_DATABASE=alertcontacts

# Firebase
FIREBASE_CREDENTIALS=          # path vers service account JSON
FIREBASE_DATABASE_URL=
FIREBASE_STORAGE_BUCKET=

# File d'attente
QUEUE_CONNECTION=database

# Admin
ADMIN_EMAILS=                  # emails autorisés à accéder au panel
```

---

## Comportement attendu de Claude sur ce projet

- Raisonner en contexte **Laravel 12 + PHP 8.2** avec les conventions Laravel (Eloquent, Sanctum, Jobs, etc.)
- Les **Services** contiennent la logique métier — ne jamais mettre de logique complexe dans les contrôleurs
- Pour les requêtes géospatiales sur SafeZones → utiliser `EloquentSpatial` ; pour les DangerZones → Haversine
- Le **système de cooldowns** est en base de données (table `cooldowns`), pas Redis — respecter ce choix
- Les **notifications** passent toujours par `NotificationService` (jamais appel direct à `FirebaseNotificationService`)
- Tout endpoint protégé nécessite `auth:sanctum` — ne jamais exposer de données utilisateur sans authentification
- Les **migrations** sont irréversibles en production — toujours vérifier l'impact avant de modifier le schéma
