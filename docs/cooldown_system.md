# Système de Cooldown - AlertContact Backend

## Vue d'ensemble

Le système de cooldown d'AlertContact empêche le spam de notifications en imposant des délais minimum entre les alertes. Il utilise Redis pour la persistance et la performance.

## Architecture

### Services impliqués

1. **CooldownService** : Service principal de gestion des cooldowns
2. **NotificationService** : Utilise le cooldown pour les notifications push
3. **GeoprocessingService** : Applique le cooldown pour les zones de danger
4. **FirebaseNotificationService** : Cooldown additionnel pour FCM

### Types de cooldowns

| Type | Durée | Description |
|------|-------|-------------|
| `danger_zone_alert` | 15 minutes | Alertes de zones de danger (NotificationService) |
| `danger_zone_{id}_user_{id}` | 24 heures | Entrée en zone de danger (GeoprocessingService) |
| `safe_zone_entry` | 5 minutes | Entrée en zone de sécurité |
| `safe_zone_exit` | 5 minutes | Sortie de zone de sécurité |
| `invitation` | 1 heure | Notifications d'invitation |
| `fcm_cooldown` | 15 minutes | Cooldown Firebase (par token + contenu) |

## Implémentation

### Structure des clés Redis

```
cooldown:{type}_{user_id}_{zone_id}
```

Exemples :
- `cooldown:danger_zone_alert_1_6` : Alerte zone danger pour user 1, zone 6
- `cooldown:safe_zone_entry_2_3` : Entrée zone sécurité pour user 2, zone 3
- `cooldown:invitation_1_5` : Invitation de user 1 vers user 5

### CooldownService API

```php
// Vérifier si en cooldown
$isInCooldown = $cooldownService->isInCooldown($key);

// Définir un cooldown
$cooldownService->setCooldown($key, $durationSeconds);

// Supprimer un cooldown
$cooldownService->removeCooldown($key);

// Obtenir le temps restant
$remainingSeconds = $cooldownService->getRemainingTime($key);

// Statistiques
$stats = $cooldownService->getStats();

// Nettoyage des expirés
$cleaned = $cooldownService->cleanupExpired();
```

## Gestion via Artisan

### Commande principale

```bash
php artisan cooldown:manage {action} [options]
```

### Actions disponibles

#### 1. Statistiques
```bash
php artisan cooldown:manage stats
```
Affiche le nombre total, actif et expiré de cooldowns.

#### 2. Lister les cooldowns actifs
```bash
php artisan cooldown:manage list
```
Affiche tous les cooldowns actifs avec leurs détails.

#### 3. Supprimer un cooldown spécifique
```bash
# Supprimer pour un utilisateur et une zone spécifiques
php artisan cooldown:manage remove --user=1 --zone=6 --type=danger_zone_alert

# Le type par défaut est 'danger_zone_alert'
php artisan cooldown:manage remove --user=1 --zone=6
```

#### 4. Nettoyage des cooldowns
```bash
# Nettoyer seulement les expirés
php artisan cooldown:manage clear

# Nettoyer par utilisateur
php artisan cooldown:manage clear --user=1

# Nettoyer par zone
php artisan cooldown:manage clear --zone=6

# Nettoyer par type
php artisan cooldown:manage clear --type=danger_zone_alert

# Nettoyer spécifiquement
php artisan cooldown:manage clear --user=1 --zone=6 --type=danger_zone_alert
```

### Options disponibles

| Option | Description | Exemple |
|--------|-------------|---------|
| `--user=ID` | ID utilisateur spécifique | `--user=1` |
| `--zone=ID` | ID zone spécifique | `--zone=6` |
| `--type=TYPE` | Type de cooldown | `--type=danger_zone_alert` |

## Flux de traitement

### 1. Zone de danger (GeoprocessingService)

```php
// Clé : danger_zone_{zone_id}_user_{user_id}
// Durée : 24 heures
$cooldownKey = "danger_zone_{$zone->id}_user_{$location->user_id}";

if ($this->cooldownService->isInCooldown($cooldownKey)) {
    // Skip l'alerte
    return;
}

// Envoyer notification
$this->notificationService->sendDangerZoneAlert($userId, $zone, $distance);

// Activer cooldown 24h
$this->cooldownService->setCooldown($cooldownKey, 24 * 60 * 60);
```

### 2. Notification push (NotificationService)

```php
// Clé : danger_zone_alert_{user_id}_{zone_id}
// Durée : 15 minutes
$cooldownKey = "danger_zone_alert_{$userId}_{$zone->id}";

if ($this->cooldownService->isInCooldown($cooldownKey)) {
    // Skip la notification
    return false;
}

// Envoyer via Firebase
$success = $this->firebaseService->sendDangerZoneAlert($user, $zone, $distance);

if ($success) {
    // Activer cooldown 15 min
    $this->cooldownService->setCooldown($cooldownKey, 900);
}
```

## Debugging et monitoring

### Logs disponibles

Les cooldowns génèrent des logs détaillés :

```
[DEBUG] Cooldown check {"key":"danger_zone_alert_1_6","in_cooldown":true}
[DEBUG] Cooldown set {"key":"danger_zone_alert_1_6","duration_seconds":900}
[DEBUG] Cooldown removed {"key":"danger_zone_alert_1_6","existed":true}
[INFO] Danger zone alert skipped - cooldown active {"user_id":1,"zone_id":6}
```

### Surveillance Redis

```bash
# Voir toutes les clés de cooldown
redis-cli KEYS "cooldown:*"

# Voir le TTL d'une clé
redis-cli TTL "cooldown:danger_zone_alert_1_6"

# Supprimer manuellement
redis-cli DEL "cooldown:danger_zone_alert_1_6"
```

## Cas d'usage de développement

### 1. Test d'alertes répétées
```bash
# Supprimer le cooldown pour tester immédiatement
php artisan cooldown:manage remove --user=1 --zone=6
```

### 2. Reset complet pour tests
```bash
# Attention : supprime TOUS les cooldowns actifs
php artisan cooldown:manage clear
# Confirmer avec 'yes' puis 'yes' pour les actifs
```

### 3. Monitoring en production
```bash
# Vérifier l'état général
php artisan cooldown:manage stats

# Voir les cooldowns actifs
php artisan cooldown:manage list

# Nettoyer les expirés (maintenance)
php artisan cooldown:manage clear
```

## Configuration

### Durées par défaut

Les durées sont définies dans les services :

- **NotificationService** : 900s (15 min) pour `danger_zone_alert`
- **GeoprocessingService** : 86400s (24h) pour `danger_zone_{id}_user_{id}`
- **FirebaseNotificationService** : 900s (15 min) pour `fcm_cooldown`

### Modification des durées

Pour modifier les durées, éditer les services correspondants :

```php
// Dans NotificationService.php
$this->cooldownService->setCooldown($cooldownKey, 900); // 15 minutes

// Dans GeoprocessingService.php  
$this->cooldownService->setCooldown($cooldownKey, 24 * 60 * 60); // 24 heures
```

## Sécurité et robustesse

### Gestion d'erreurs

- En cas d'erreur Redis, `isInCooldown()` retourne `false` pour éviter de bloquer les notifications critiques
- Tous les appels Redis sont dans des try/catch avec logs d'erreur
- Les méthodes retournent des valeurs par défaut sûres

### Performance

- Utilisation de Redis pour des accès O(1)
- Clés avec TTL automatique (pas de nettoyage manuel requis)
- Logs de debug désactivables en production

### Monitoring

- Statistiques disponibles via `getStats()`
- Logs structurés pour monitoring externe
- Commandes Artisan pour administration

## Troubleshooting

### Problème : Pas d'alertes reçues
1. Vérifier les cooldowns actifs : `php artisan cooldown:manage list`
2. Supprimer le cooldown spécifique : `php artisan cooldown:manage remove --user=X --zone=Y`

### Problème : Trop d'alertes (spam)
1. Vérifier que les cooldowns sont bien appliqués dans les logs
2. Augmenter les durées de cooldown dans les services

### Problème : Redis inaccessible
- Les services continuent de fonctionner (mode dégradé)
- Vérifier la connexion Redis dans `.env`
- Redémarrer Redis si nécessaire

## Évolutions futures

### Cooldowns configurables
- Ajouter une table `cooldown_settings` pour des durées par utilisateur
- Interface admin pour modifier les durées
- Cooldowns différents selon la criticité des zones

### Cooldowns intelligents
- Réduction automatique selon l'historique utilisateur
- Cooldowns adaptatifs selon l'heure/localisation
- Exceptions pour les zones critiques