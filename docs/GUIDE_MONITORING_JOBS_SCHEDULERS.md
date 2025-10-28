# Guide de Monitoring des Jobs et Schedulers - AlertContact

## 📋 Vue d'ensemble du système

### Jobs identifiés
1. **ProcessLocationBatch** - Traitement géospatial des positions
2. **SendInvitationResponseNotificationJob** - Notifications de réponse d'invitation
3. **SendSafeZoneExitReminders** - Rappels de sortie de zone de sécurité

### Schedulers configurés
1. **SendSafeZoneExitReminders** - Toutes les 5 minutes (bootstrap/app.php)

### Commandes artisan personnalisées
1. **cooldown:manage** - Gestion des cooldowns
2. **safezone:send-reminders** - Envoi manuel des rappels

---

## 🔍 Méthodes de vérification

### 1. Vérifier l'état des queues

```bash
# Voir les jobs en attente
php artisan queue:work --once --verbose

# Statistiques des queues
php artisan queue:monitor

# Voir les jobs échoués
php artisan queue:failed

# Redémarrer les jobs échoués
php artisan queue:retry all
```

### 2. Vérifier le scheduler

```bash
# Lister toutes les tâches planifiées
php artisan schedule:list

# Exécuter le scheduler manuellement (pour test)
php artisan schedule:run

# Voir les prochaines exécutions
php artisan schedule:work
```

### 3. Monitoring en temps réel

```bash
# Surveiller les queues en continu
php artisan queue:work --verbose

# Avec timeout et retry
php artisan queue:work --timeout=60 --tries=3 --verbose
```

---

## 📊 Commandes de diagnostic spécifiques

### Vérifier les cooldowns
```bash
# Statistiques des cooldowns
php artisan cooldown:manage stats

# Lister tous les cooldowns actifs
php artisan cooldown:manage list

# Nettoyer les cooldowns expirés
php artisan cooldown:manage clear
```

### Tester les rappels de zone de sécurité
```bash
# Exécution manuelle
php artisan safezone:send-reminders

# Vérifier les logs
tail -f storage/logs/laravel.log | grep "safe zone"
```

---

## 🔧 Configuration de monitoring avancé

### 1. Activer Telescope (déjà configuré)
```bash
# Publier les assets Telescope
php artisan telescope:publish

# Accéder à l'interface web
# http://votre-domaine/telescope
```

### 2. Logs détaillés
Ajouter dans `.env` :
```env
LOG_LEVEL=debug
QUEUE_CONNECTION=database
```

### 3. Monitoring des performances
```bash
# Voir les métriques des jobs
php artisan queue:monitor default,geoprocessing --max=100
```

---

## 🚨 Alertes et notifications d'erreur

### 1. Configurer les notifications d'échec
Créer un listener pour les jobs échoués :

```php
// Dans EventServiceProvider
'Illuminate\Queue\Events\JobFailed' => [
    'App\Listeners\LogFailedJob',
],
```

### 2. Surveillance des logs
```bash
# Surveiller les erreurs en temps réel
tail -f storage/logs/laravel.log | grep ERROR

# Filtrer par type de job
tail -f storage/logs/laravel.log | grep "ProcessLocationBatch\|SendInvitation\|SendSafeZone"
```

---

## 📈 Métriques importantes à surveiller

### 1. Performance des jobs
- Temps d'exécution moyen
- Taux d'échec
- Nombre de tentatives

### 2. Scheduler
- Exécutions manquées
- Chevauchements (overlapping)
- Durée d'exécution

### 3. Queues
- Nombre de jobs en attente
- Jobs bloqués
- Mémoire utilisée

---

## 🛠️ Scripts de vérification automatique

### Script de santé globale
```bash
#!/bin/bash
echo "=== Vérification des Jobs et Schedulers AlertContact ==="

echo "1. État des queues:"
php artisan queue:monitor

echo "2. Jobs échoués:"
php artisan queue:failed

echo "3. Prochaines tâches planifiées:"
php artisan schedule:list

echo "4. Test du scheduler:"
php artisan schedule:run --verbose

echo "5. Statistiques des cooldowns:"
php artisan cooldown:manage stats
```

### Surveillance continue
```bash
# Lancer en arrière-plan
nohup php artisan queue:work --verbose --timeout=60 > queue.log 2>&1 &

# Surveiller le scheduler
nohup php artisan schedule:work > schedule.log 2>&1 &
```

---

## 🔍 Debugging spécifique par job

### ProcessLocationBatch
```bash
# Vérifier les logs de géoprocessing
grep "Processing location batch" storage/logs/laravel.log

# Surveiller la queue geoprocessing
php artisan queue:work geoprocessing --verbose
```

### SendInvitationResponseNotificationJob
```bash
# Logs des notifications d'invitation
grep "Envoi de notification de réponse" storage/logs/laravel.log

# Vérifier Firebase
grep "Firebase" storage/logs/laravel.log
```

### SendSafeZoneExitReminders
```bash
# Logs des rappels
grep "safe zone exit reminders" storage/logs/laravel.log

# Vérifier les alertes en attente
php artisan tinker
>>> App\Models\PendingSafeZoneAlert::needingReminder(5)->count()
```

---

## ⚡ Actions correctives courantes

### Jobs bloqués
```bash
# Redémarrer les workers
php artisan queue:restart

# Nettoyer les jobs échoués
php artisan queue:flush
```

### Scheduler qui ne fonctionne pas
```bash
# Vérifier le cron
crontab -l

# Ajouter si manquant:
# * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### Problèmes de mémoire
```bash
# Augmenter la limite mémoire
php artisan queue:work --memory=512

# Redémarrer après X jobs
php artisan queue:work --max-jobs=100
```

---

## 📱 Intégration avec l'app mobile

### Vérifier la réception des notifications
1. Tester les notifications Firebase
2. Vérifier les tokens FCM
3. Contrôler les payloads

### Monitoring des positions
1. Vérifier les batches de géolocalisation
2. Surveiller les alertes de zone
3. Contrôler les cooldowns

---

## 🎯 Checklist de vérification quotidienne

- [ ] Vérifier les jobs échoués
- [ ] Contrôler les logs d'erreur
- [ ] Tester le scheduler manuellement
- [ ] Vérifier les métriques Telescope
- [ ] Contrôler l'état des queues
- [ ] Tester les notifications Firebase
- [ ] Vérifier les cooldowns actifs

---

## 📞 En cas de problème

1. **Vérifier les logs** : `storage/logs/laravel.log`
2. **Consulter Telescope** : `/telescope`
3. **Tester manuellement** : Commandes artisan
4. **Redémarrer les services** : Queue workers et scheduler
5. **Vérifier la configuration** : `.env` et `config/`

Ce guide vous permet de maintenir un monitoring complet de votre système de jobs et schedulers pour garantir le bon fonctionnement d'AlertContact.