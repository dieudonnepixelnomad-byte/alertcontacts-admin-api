# 🚀 Guide Complet - Jobs, Schedules & Système de Nettoyage - AlertContact

## 📋 Table des Matières
1. [🎯 Vue d'ensemble](#vue-densemble)
2. [🔧 Configuration Initiale](#configuration-initiale)
3. [🚀 Démarrage Complet des Services](#démarrage-complet-des-services)
4. [🧹 Système de Nettoyage](#système-de-nettoyage)
5. [📊 Surveillance et Monitoring](#surveillance-et-monitoring)
6. [🔄 Maintenance et Dépannage](#maintenance-et-dépannage)
7. [🛡️ Sécurité et Backup](#sécurité-et-backup)

---

## 🎯 Vue d'ensemble

Ce guide couvre la mise en place complète de tous les services backend d'AlertContact :
- **Queue Workers** : Traitement des jobs asynchrones
- **Scheduler** : Exécution des tâches planifiées
- **Système de Nettoyage** : Maintenance automatique des données
- **Monitoring** : Surveillance et alertes

**🎯 Objectif** : Tous les services doivent fonctionner de manière persistante, même après fermeture du terminal SSH.

---

## 🔧 Configuration Initiale

### 1. Variables d'Environnement (.env)

Ajoutez ces variables à votre fichier `.env` :

```bash
# === CONFIGURATION JOBS & QUEUES ===
QUEUE_CONNECTION=database
QUEUE_FAILED_DRIVER=database

# Nombre de workers simultanés
QUEUE_WORKERS=3

# Timeout des jobs (en secondes)
QUEUE_TIMEOUT=300

# Retry des jobs échoués
QUEUE_RETRY_AFTER=90

# === CONFIGURATION SCHEDULER ===
SCHEDULER_ENABLED=true
SCHEDULER_TIMEZONE=Europe/Paris

# === CONFIGURATION NETTOYAGE ===
CLEANUP_ENABLED=true
CLEANUP_ADMIN_EMAIL=admin@alertcontact.com

# Rétentions personnalisées (en jours)
CLEANUP_USER_LOCATIONS_DAYS=30
CLEANUP_TELESCOPE_DAYS=7
CLEANUP_USER_ACTIVITIES_DAYS=90
CLEANUP_SAFE_ZONE_EVENTS_DAYS=180
CLEANUP_JOBS_DAYS=7
CLEANUP_FAILED_JOBS_DAYS=30
CLEANUP_JOB_BATCHES_DAYS=30

# Optimisation
CLEANUP_OPTIMIZE_TABLES=true
CLEANUP_DETAILED_LOGS=true
CLEANUP_BATCH_SIZE=1000

# === CONFIGURATION MONITORING ===
MONITORING_ENABLED=true
MONITORING_EMAIL=monitoring@alertcontact.com
MONITORING_SLACK_WEBHOOK=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK

# === CONFIGURATION LOGS ===
LOG_CHANNEL=stack
LOG_LEVEL=info
LOG_DEPRECATIONS_CHANNEL=null
```

### 2. Permissions et Répertoires

```bash
# Créer les répertoires nécessaires
mkdir -p storage/logs/jobs
mkdir -p storage/logs/scheduler
mkdir -p storage/logs/cleanup
mkdir -p storage/pids

# Définir les permissions
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
chown -R www-data:www-data storage/
chown -R www-data:www-data bootstrap/cache/
```

---

## 🚀 Démarrage Complet des Services

### 1. Script de Démarrage Principal

Créez le fichier `start_all_services.sh` :

```bash
#!/bin/bash

# === SCRIPT DE DÉMARRAGE COMPLET ALERTCONTACT ===
# Ce script démarre tous les services backend de manière persistante

set -e

# Configuration
PROJECT_PATH="/home/u918130518/domains/alertcontacts.net/public_html/mobile"
PHP_PATH="/usr/bin/php"
LOG_PATH="$PROJECT_PATH/storage/logs"
PID_PATH="$PROJECT_PATH/storage/pids"

# Couleurs pour les logs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction de logging
log() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1"
}

warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1"
}

# Vérifier que nous sommes dans le bon répertoire
cd "$PROJECT_PATH" || {
    error "Impossible d'accéder au répertoire $PROJECT_PATH"
    exit 1
}

log "🚀 Démarrage des services AlertContact..."

# === 1. ARRÊTER LES SERVICES EXISTANTS ===
log "🛑 Arrêt des services existants..."

# Arrêter les processus existants
pkill -f "artisan queue:work" 2>/dev/null || true
pkill -f "artisan schedule:work" 2>/dev/null || true
pkill -f "artisan horizon" 2>/dev/null || true

# Nettoyer les anciens PIDs
rm -f "$PID_PATH"/*.pid

sleep 2

# === 2. VÉRIFICATIONS PRÉALABLES ===
log "🔍 Vérifications préalables..."

# Vérifier PHP
if ! command -v php &> /dev/null; then
    error "PHP n'est pas installé ou non accessible"
    exit 1
fi

# Vérifier Laravel
if [ ! -f "artisan" ]; then
    error "Fichier artisan non trouvé. Êtes-vous dans le bon répertoire ?"
    exit 1
fi

# Vérifier la base de données
log "📊 Test de connexion à la base de données..."
if ! $PHP_PATH artisan tinker --execute="DB::connection()->getPdo(); echo 'DB OK';" 2>/dev/null | grep -q "DB OK"; then
    error "Impossible de se connecter à la base de données"
    exit 1
fi

# Optimiser l'application
log "⚡ Optimisation de l'application..."
$PHP_PATH artisan config:cache
$PHP_PATH artisan route:cache
$PHP_PATH artisan view:cache

# === 3. DÉMARRAGE DU SCHEDULER ===
log "📅 Démarrage du Scheduler Laravel..."

nohup $PHP_PATH artisan schedule:work \
    --verbose \
    > "$LOG_PATH/scheduler/scheduler.log" 2>&1 &

SCHEDULER_PID=$!
echo $SCHEDULER_PID > "$PID_PATH/scheduler.pid"
log "✅ Scheduler démarré (PID: $SCHEDULER_PID)"

# === 4. DÉMARRAGE DES QUEUE WORKERS ===
log "⚙️ Démarrage des Queue Workers..."

# Worker principal (queue par défaut)
nohup $PHP_PATH artisan queue:work \
    --queue=default,high,low \
    --sleep=3 \
    --tries=3 \
    --max-time=3600 \
    --memory=512 \
    --timeout=300 \
    > "$LOG_PATH/jobs/worker-default.log" 2>&1 &

WORKER1_PID=$!
echo $WORKER1_PID > "$PID_PATH/worker-default.pid"
log "✅ Worker principal démarré (PID: $WORKER1_PID)"

# Worker pour les notifications (haute priorité)
nohup $PHP_PATH artisan queue:work \
    --queue=notifications,high \
    --sleep=1 \
    --tries=5 \
    --max-time=3600 \
    --memory=256 \
    --timeout=120 \
    > "$LOG_PATH/jobs/worker-notifications.log" 2>&1 &

WORKER2_PID=$!
echo $WORKER2_PID > "$PID_PATH/worker-notifications.pid"
log "✅ Worker notifications démarré (PID: $WORKER2_PID)"

# Worker pour le nettoyage (basse priorité)
nohup $PHP_PATH artisan queue:work \
    --queue=cleanup,low \
    --sleep=5 \
    --tries=2 \
    --max-time=7200 \
    --memory=1024 \
    --timeout=1800 \
    > "$LOG_PATH/jobs/worker-cleanup.log" 2>&1 &

WORKER3_PID=$!
echo $WORKER3_PID > "$PID_PATH/worker-cleanup.pid"
log "✅ Worker nettoyage démarré (PID: $WORKER3_PID)"

# === 5. DÉMARRAGE DU MONITORING ===
log "📊 Démarrage du système de monitoring..."

nohup $PHP_PATH artisan queue:monitor default,high,low,notifications,cleanup \
    --max=100 \
    > "$LOG_PATH/jobs/monitor.log" 2>&1 &

MONITOR_PID=$!
echo $MONITOR_PID > "$PID_PATH/monitor.pid"
log "✅ Monitoring démarré (PID: $MONITOR_PID)"

# === 6. VÉRIFICATION DES SERVICES ===
log "🔍 Vérification des services..."

sleep 5

# Vérifier que tous les processus sont actifs
SERVICES_OK=true

if ! kill -0 $SCHEDULER_PID 2>/dev/null; then
    error "Le scheduler n'est pas actif"
    SERVICES_OK=false
fi

if ! kill -0 $WORKER1_PID 2>/dev/null; then
    error "Le worker principal n'est pas actif"
    SERVICES_OK=false
fi

if ! kill -0 $WORKER2_PID 2>/dev/null; then
    error "Le worker notifications n'est pas actif"
    SERVICES_OK=false
fi

if ! kill -0 $WORKER3_PID 2>/dev/null; then
    error "Le worker nettoyage n'est pas actif"
    SERVICES_OK=false
fi

if [ "$SERVICES_OK" = true ]; then
    log "🎉 Tous les services sont démarrés avec succès !"
    log "📋 Résumé des services :"
    log "   - Scheduler: PID $SCHEDULER_PID"
    log "   - Worker Principal: PID $WORKER1_PID"
    log "   - Worker Notifications: PID $WORKER2_PID"
    log "   - Worker Nettoyage: PID $WORKER3_PID"
    log "   - Monitoring: PID $MONITOR_PID"
    log ""
    log "📊 Pour surveiller les services :"
    log "   - Logs scheduler: tail -f $LOG_PATH/scheduler/scheduler.log"
    log "   - Logs workers: tail -f $LOG_PATH/jobs/worker-*.log"
    log "   - Status: ./check_services_status.sh"
else
    error "❌ Certains services n'ont pas pu démarrer correctement"
    exit 1
fi

# === 7. CRÉATION DU FICHIER DE STATUS ===
cat > "$PROJECT_PATH/services_status.json" << EOF
{
    "started_at": "$(date -Iseconds)",
    "services": {
        "scheduler": {
            "pid": $SCHEDULER_PID,
            "log_file": "$LOG_PATH/scheduler/scheduler.log"
        },
        "worker_default": {
            "pid": $WORKER1_PID,
            "log_file": "$LOG_PATH/jobs/worker-default.log"
        },
        "worker_notifications": {
            "pid": $WORKER2_PID,
            "log_file": "$LOG_PATH/jobs/worker-notifications.log"
        },
        "worker_cleanup": {
            "pid": $WORKER3_PID,
            "log_file": "$LOG_PATH/jobs/worker-cleanup.log"
        },
        "monitor": {
            "pid": $MONITOR_PID,
            "log_file": "$LOG_PATH/jobs/monitor.log"
        }
    }
}
EOF

log "💾 Fichier de status créé : services_status.json"
log "🚀 Démarrage terminé avec succès !"
```

### 2. Script de Vérification des Services

Créez le fichier `check_services_status.sh` :

```bash
#!/bin/bash

# === SCRIPT DE VÉRIFICATION DES SERVICES ===

PROJECT_PATH="/home/u918130518/domains/alertcontacts.net/public_html/mobile"
PID_PATH="$PROJECT_PATH/storage/pids"

# Couleurs
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}🔍 Vérification des services AlertContact${NC}"
echo "=================================================="

# Fonction pour vérifier un service
check_service() {
    local service_name=$1
    local pid_file=$2
    
    if [ -f "$pid_file" ]; then
        local pid=$(cat "$pid_file")
        if kill -0 "$pid" 2>/dev/null; then
            echo -e "✅ $service_name: ${GREEN}ACTIF${NC} (PID: $pid)"
            return 0
        else
            echo -e "❌ $service_name: ${RED}ARRÊTÉ${NC} (PID obsolète: $pid)"
            return 1
        fi
    else
        echo -e "❌ $service_name: ${RED}NON DÉMARRÉ${NC} (pas de fichier PID)"
        return 1
    fi
}

# Vérifier tous les services
SERVICES_OK=0

check_service "Scheduler" "$PID_PATH/scheduler.pid" && ((SERVICES_OK++))
check_service "Worker Principal" "$PID_PATH/worker-default.pid" && ((SERVICES_OK++))
check_service "Worker Notifications" "$PID_PATH/worker-notifications.pid" && ((SERVICES_OK++))
check_service "Worker Nettoyage" "$PID_PATH/worker-cleanup.pid" && ((SERVICES_OK++))
check_service "Monitoring" "$PID_PATH/monitor.pid" && ((SERVICES_OK++))

echo "=================================================="

if [ $SERVICES_OK -eq 5 ]; then
    echo -e "🎉 ${GREEN}Tous les services sont opérationnels !${NC}"
else
    echo -e "⚠️ ${YELLOW}$SERVICES_OK/5 services actifs${NC}"
    echo -e "💡 Pour redémarrer : ${YELLOW}./restart_services.sh${NC}"
fi

# Afficher les statistiques des queues
echo ""
echo -e "${GREEN}📊 Statistiques des queues :${NC}"
cd "$PROJECT_PATH"
php artisan queue:monitor default,high,low,notifications,cleanup --once 2>/dev/null || echo "Impossible de récupérer les stats"
```

### 3. Script de Redémarrage

Créez le fichier `restart_services.sh` :

```bash
#!/bin/bash

# === SCRIPT DE REDÉMARRAGE DES SERVICES ===

PROJECT_PATH="/home/u918130518/domains/alertcontacts.net/public_html/mobile"
PID_PATH="$PROJECT_PATH/storage/pids"

echo "🔄 Redémarrage des services AlertContact..."

# Arrêter tous les services
echo "🛑 Arrêt des services existants..."
if [ -d "$PID_PATH" ]; then
    for pid_file in "$PID_PATH"/*.pid; do
        if [ -f "$pid_file" ]; then
            pid=$(cat "$pid_file")
            if kill -0 "$pid" 2>/dev/null; then
                kill "$pid"
                echo "   - Arrêt du processus $pid"
            fi
            rm -f "$pid_file"
        fi
    done
fi

# Attendre que les processus se terminent
sleep 3

# Redémarrer tous les services
echo "🚀 Redémarrage..."
./start_all_services.sh
```

### 4. Script d'Arrêt

Créez le fichier `stop_all_services.sh` :

```bash
#!/bin/bash

# === SCRIPT D'ARRÊT DES SERVICES ===

PROJECT_PATH="/home/u918130518/domains/alertcontacts.net/public_html/mobile"
PID_PATH="$PROJECT_PATH/storage/pids"

echo "🛑 Arrêt de tous les services AlertContact..."

# Arrêter proprement tous les services
if [ -d "$PID_PATH" ]; then
    for pid_file in "$PID_PATH"/*.pid; do
        if [ -f "$pid_file" ]; then
            service_name=$(basename "$pid_file" .pid)
            pid=$(cat "$pid_file")
            
            if kill -0 "$pid" 2>/dev/null; then
                echo "   - Arrêt de $service_name (PID: $pid)"
                kill -TERM "$pid"
                
                # Attendre l'arrêt gracieux
                for i in {1..10}; do
                    if ! kill -0 "$pid" 2>/dev/null; then
                        break
                    fi
                    sleep 1
                done
                
                # Forcer l'arrêt si nécessaire
                if kill -0 "$pid" 2>/dev/null; then
                    echo "   - Arrêt forcé de $service_name"
                    kill -KILL "$pid"
                fi
            fi
            
            rm -f "$pid_file"
        fi
    done
fi

# Nettoyer les processus orphelins
pkill -f "artisan queue:work" 2>/dev/null || true
pkill -f "artisan schedule:work" 2>/dev/null || true
pkill -f "artisan horizon" 2>/dev/null || true

echo "✅ Tous les services ont été arrêtés"
```

### 5. Rendre les Scripts Exécutables

```bash
chmod +x start_all_services.sh
chmod +x check_services_status.sh
chmod +x restart_services.sh
chmod +x stop_all_services.sh
```

---

## 🧹 Système de Nettoyage

### 1. Tables Critiques Surveillées

#### Très Critiques (accumulation rapide)
- **`user_locations`** - Positions GPS (rétention: 30 jours)
- **`telescope_entries`** - Logs debug (rétention: 7 jours)
- **`user_activities`** - Activités utilisateurs (rétention: 90 jours)

#### Critiques (accumulation modérée)
- **`safe_zone_events`** - Événements zones (rétention: 180 jours)
- **`jobs`** - Jobs traités (rétention: 7 jours)
- **`failed_jobs`** - Jobs échoués (rétention: 30 jours)
- **`job_batches`** - Lots de jobs (rétention: 30 jours)
- **`cooldowns`** - Cooldowns notifications (suppression à expiration)
- **`personal_access_tokens`** - Tokens API (suppression à expiration)

### 2. Commandes de Nettoyage

```bash
# === COMMANDES PRINCIPALES ===

# Simulation complète (voir ce qui serait supprimé)
php artisan cleanup:old-data --dry-run

# Statistiques détaillées des tables
php artisan cleanup:old-data --stats

# Nettoyage réel avec confirmation
php artisan cleanup:old-data

# Nettoyage forcé sans confirmation (pour automatisation)
php artisan cleanup:old-data --force

# === COMMANDES DE MAINTENANCE ===

# Vérifier les tâches planifiées
php artisan schedule:list

# Exécuter manuellement le scheduler (test)
php artisan schedule:run

# Voir les jobs en échec
php artisan queue:failed

# Relancer les jobs échoués
php artisan queue:retry all

# Purger les jobs échoués anciens
php artisan queue:flush
```

### 3. Configuration du Nettoyage

Le fichier `config/cleanup.php` permet de personnaliser :

```php
<?php

return [
    // Activation du système
    'enabled' => env('CLEANUP_ENABLED', true),
    
    // Rétentions par table (en jours)
    'retention_days' => [
        'user_locations' => env('CLEANUP_USER_LOCATIONS_DAYS', 30),
        'telescope_entries' => env('CLEANUP_TELESCOPE_DAYS', 7),
        'user_activities' => env('CLEANUP_USER_ACTIVITIES_DAYS', 90),
        'safe_zone_events' => env('CLEANUP_SAFE_ZONE_EVENTS_DAYS', 180),
        'jobs' => env('CLEANUP_JOBS_DAYS', 7),
        'failed_jobs' => env('CLEANUP_FAILED_JOBS_DAYS', 30),
        'job_batches' => env('CLEANUP_JOB_BATCHES_DAYS', 30),
    ],
    
    // Paramètres de performance
    'batch_size' => env('CLEANUP_BATCH_SIZE', 1000),
    'batch_delay_ms' => 25,
    'max_execution_time' => 7200, // 2 heures
    
    // Optimisation des tables
    'optimize_tables' => env('CLEANUP_OPTIMIZE_TABLES', true),
    
    // Notifications
    'admin_email' => env('CLEANUP_ADMIN_EMAIL'),
    'detailed_logs' => env('CLEANUP_DETAILED_LOGS', true),
];
```

### 4. Planification Automatique

Les tâches sont configurées dans `routes/console.php` :

```php
// Nettoyage principal quotidien
Schedule::command('cleanup:old-data --force')
    ->dailyAt('02:00')
    ->name('cleanup-old-data-daily')
    ->withoutOverlapping(120) // 2h max
    ->onFailure(function () {
        // Notification en cas d'échec
        Mail::to(config('cleanup.admin_email'))
            ->send(new CleanupFailedMail());
    });

// Nettoyages légers
Schedule::command('cleanup:expired-cooldowns')
    ->hourly()
    ->name('cleanup-cooldowns')
    ->withoutOverlapping();

Schedule::command('cleanup:expired-tokens')
    ->everySixHours()
    ->name('cleanup-tokens')
    ->withoutOverlapping();

// Rapport hebdomadaire
Schedule::command('cleanup:old-data --stats')
    ->weeklyOn(0, '08:00') // Dimanche 8h
    ->name('cleanup-weekly-stats');
```

---

## 📊 Surveillance et Monitoring

### 1. Script de Monitoring Automatique

Créez le fichier `monitor_services.sh` :

```bash
#!/bin/bash

# === MONITORING AUTOMATIQUE DES SERVICES ===

PROJECT_PATH="/home/u918130518/domains/alertcontacts.net/public_html/mobile"
LOG_PATH="$PROJECT_PATH/storage/logs/monitoring"
PID_PATH="$PROJECT_PATH/storage/pids"

# Créer le répertoire de logs si nécessaire
mkdir -p "$LOG_PATH"

# Fonction de logging avec timestamp
log_monitor() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_PATH/monitor.log"
}

# Fonction d'alerte
send_alert() {
    local message=$1
    log_monitor "ALERT: $message"
    
    # Envoyer email (si configuré)
    if [ -n "$MONITORING_EMAIL" ]; then
        echo "$message" | mail -s "AlertContact - Service Alert" "$MONITORING_EMAIL"
    fi
    
    # Webhook Slack (si configuré)
    if [ -n "$MONITORING_SLACK_WEBHOOK" ]; then
        curl -X POST -H 'Content-type: application/json' \
            --data "{\"text\":\"🚨 AlertContact Alert: $message\"}" \
            "$MONITORING_SLACK_WEBHOOK"
    fi
}

# Vérifier chaque service
check_and_restart() {
    local service_name=$1
    local pid_file=$2
    local restart_command=$3
    
    if [ -f "$pid_file" ]; then
        local pid=$(cat "$pid_file")
        if ! kill -0 "$pid" 2>/dev/null; then
            log_monitor "Service $service_name arrêté (PID: $pid), redémarrage..."
            send_alert "Service $service_name s'est arrêté et va être redémarré"
            
            # Redémarrer le service
            eval "$restart_command"
            
            log_monitor "Service $service_name redémarré"
        else
            log_monitor "Service $service_name OK (PID: $pid)"
        fi
    else
        log_monitor "Service $service_name non démarré, démarrage..."
        send_alert "Service $service_name n'était pas démarré"
        
        # Démarrer le service
        eval "$restart_command"
        
        log_monitor "Service $service_name démarré"
    fi
}

# Monitoring principal
log_monitor "=== Début du monitoring ==="

cd "$PROJECT_PATH"

# Vérifier chaque service
check_and_restart "Scheduler" "$PID_PATH/scheduler.pid" \
    "nohup php artisan schedule:work > storage/logs/scheduler/scheduler.log 2>&1 & echo \$! > $PID_PATH/scheduler.pid"

check_and_restart "Worker-Default" "$PID_PATH/worker-default.pid" \
    "nohup php artisan queue:work --queue=default,high,low --sleep=3 --tries=3 > storage/logs/jobs/worker-default.log 2>&1 & echo \$! > $PID_PATH/worker-default.pid"

check_and_restart "Worker-Notifications" "$PID_PATH/worker-notifications.pid" \
    "nohup php artisan queue:work --queue=notifications,high --sleep=1 --tries=5 > storage/logs/jobs/worker-notifications.log 2>&1 & echo \$! > $PID_PATH/worker-notifications.pid"

# Vérifier l'espace disque
DISK_USAGE=$(df "$PROJECT_PATH" | awk 'NR==2 {print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -gt 85 ]; then
    send_alert "Espace disque critique: ${DISK_USAGE}% utilisé"
fi

# Vérifier la taille des logs
LOG_SIZE=$(du -sm "$PROJECT_PATH/storage/logs" | cut -f1)
if [ "$LOG_SIZE" -gt 1000 ]; then # Plus de 1GB
    log_monitor "Logs volumineux détectés: ${LOG_SIZE}MB"
    # Nettoyer les anciens logs
    find "$PROJECT_PATH/storage/logs" -name "*.log" -mtime +7 -delete
fi

log_monitor "=== Fin du monitoring ==="
```

### 2. Configuration Cron pour le Monitoring

Ajoutez ces tâches à votre crontab :

```bash
# Éditer le crontab
crontab -e

# Ajouter ces lignes :

# Monitoring des services (toutes les 5 minutes)
*/5 * * * * /path/to/alertcontacts-admin/monitor_services.sh

# Vérification complète (toutes les heures)
0 * * * * /path/to/alertcontacts-admin/check_services_status.sh >> /path/to/alertcontacts-admin/storage/logs/monitoring/hourly_check.log

# Nettoyage des logs de monitoring (quotidien)
0 3 * * * find /path/to/alertcontacts-admin/storage/logs/monitoring -name "*.log" -mtime +30 -delete

# Backup des PIDs (toutes les 6 heures)
0 */6 * * * cp -r /path/to/alertcontacts-admin/storage/pids /path/to/alertcontacts-admin/storage/pids.backup

# Rapport de santé quotidien
0 9 * * * /path/to/alertcontacts-admin/daily_health_report.sh
```

### 3. Rapport de Santé Quotidien

Créez le fichier `daily_health_report.sh` :

```bash
#!/bin/bash

# === RAPPORT DE SANTÉ QUOTIDIEN ===

PROJECT_PATH="/home/u918130518/domains/alertcontacts.net/public_html/mobile"
REPORT_PATH="$PROJECT_PATH/storage/logs/reports"

mkdir -p "$REPORT_PATH"

REPORT_FILE="$REPORT_PATH/health_report_$(date +%Y%m%d).log"

{
    echo "=== RAPPORT DE SANTÉ ALERTCONTACT - $(date) ==="
    echo ""
    
    echo "🔍 STATUT DES SERVICES :"
    ./check_services_status.sh
    echo ""
    
    echo "📊 STATISTIQUES DES QUEUES :"
    cd "$PROJECT_PATH"
    php artisan queue:monitor default,high,low,notifications,cleanup --once
    echo ""
    
    echo "🧹 STATISTIQUES DE NETTOYAGE :"
    php artisan cleanup:old-data --stats
    echo ""
    
    echo "💾 ESPACE DISQUE :"
    df -h "$PROJECT_PATH"
    echo ""
    
    echo "📁 TAILLE DES LOGS :"
    du -sh "$PROJECT_PATH/storage/logs"/*
    echo ""
    
    echo "⚠️ JOBS ÉCHOUÉS (dernières 24h) :"
    php artisan queue:failed | head -20
    echo ""
    
    echo "🔄 DERNIÈRES EXÉCUTIONS DU SCHEDULER :"
    tail -20 "$PROJECT_PATH/storage/logs/scheduler/scheduler.log"
    
} > "$REPORT_FILE"

# Envoyer le rapport par email si configuré
if [ -n "$MONITORING_EMAIL" ]; then
    mail -s "AlertContact - Rapport de Santé Quotidien" "$MONITORING_EMAIL" < "$REPORT_FILE"
fi

echo "📋 Rapport de santé généré : $REPORT_FILE"
```

---

## 🔄 Maintenance et Dépannage

### 1. Commandes de Diagnostic

```bash
# === DIAGNOSTIC COMPLET ===

# Vérifier tous les processus Laravel
ps aux | grep -E "(artisan|php)" | grep -v grep

# Vérifier les ports utilisés
netstat -tulpn | grep php

# Vérifier l'utilisation mémoire
free -h
ps aux --sort=-%mem | head -10

# Vérifier l'espace disque
df -h
du -sh /path/to/alertcontacts-admin/storage/*

# Vérifier les logs d'erreurs
tail -50 /path/to/alertcontacts-admin/storage/logs/laravel.log

# Tester la connexion DB
php artisan tinker --execute="DB::connection()->getPdo(); echo 'DB OK';"

# === DIAGNOSTIC DES QUEUES ===

# Voir les jobs en attente
php artisan queue:work --once --verbose

# Statistiques détaillées
php artisan horizon:status
php artisan queue:monitor default,high,low --once

# Jobs échoués
php artisan queue:failed
php artisan queue:retry all

# === DIAGNOSTIC DU SCHEDULER ===

# Voir toutes les tâches
php artisan schedule:list

# Tester une exécution
php artisan schedule:run --verbose

# Voir les logs du scheduler
tail -f storage/logs/scheduler/scheduler.log
```

### 2. Procédures de Récupération

#### Récupération après Crash Serveur

```bash
#!/bin/bash
# recovery_after_crash.sh

echo "🚨 Procédure de récupération après crash..."

# 1. Nettoyer les anciens PIDs
rm -f /path/to/alertcontacts-admin/storage/pids/*.pid

# 2. Vérifier l'intégrité de la DB
php artisan migrate:status

# 3. Nettoyer les caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 4. Reconstruire les caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Vérifier les permissions
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/

# 6. Redémarrer tous les services
./start_all_services.sh

echo "✅ Récupération terminée"
```

#### Récupération des Jobs Bloqués

```bash
#!/bin/bash
# recover_stuck_jobs.sh

echo "🔧 Récupération des jobs bloqués..."

cd /path/to/alertcontacts-admin

# 1. Arrêter tous les workers
pkill -f "artisan queue:work"

# 2. Identifier les jobs bloqués
php artisan queue:failed

# 3. Nettoyer les jobs anciens (plus de 24h)
php artisan queue:prune-failed --hours=24

# 4. Relancer les jobs récents
php artisan queue:retry all

# 5. Redémarrer les workers
./start_all_services.sh

echo "✅ Jobs récupérés et workers redémarrés"
```

### 3. Optimisation des Performances

```bash
# === OPTIMISATION RÉGULIÈRE ===

# Optimiser les tables de la DB
php artisan tinker --execute="
DB::statement('OPTIMIZE TABLE user_locations');
DB::statement('OPTIMIZE TABLE telescope_entries');
DB::statement('OPTIMIZE TABLE jobs');
DB::statement('OPTIMIZE TABLE failed_jobs');
echo 'Tables optimisées';
"

# Nettoyer les sessions expirées
php artisan session:gc

# Nettoyer le cache applicatif
php artisan cache:prune-stale-tags

# Compresser les anciens logs
find storage/logs -name "*.log" -mtime +7 -exec gzip {} \;

# Analyser les performances
php artisan telescope:prune --hours=168 # Garder 7 jours
```

---

## 🛡️ Sécurité et Backup

### 1. Backup Automatique - Configuration Hostinger

**Important** : Sur Hostinger, l'espace disque est limité, donc le système de backup est optimisé pour cet environnement.

#### Structure des Backups Hostinger
```
/home/u918130518/backups/alertcontact/
├── config_20241120_143022.tar.gz          # Configuration Laravel
├── database_20241120_143022.sql           # Dump de la base de données
├── app_critical_20241120_143022.tar.gz    # Fichiers critiques de l'app
├── pids_20241120_143022/                   # PIDs des services
├── logs_20241120_143022/                   # Logs des 7 derniers jours
└── backup_info_20241120_143022.txt         # Métadonnées du backup
```

#### Spécificités Hostinger
- **Rétention** : 15 jours (au lieu de 30) pour économiser l'espace
- **Chemin projet** : `/home/u918130518/domains/alertcontacts.net/public_html/mobile`
- **Chemin backup** : `/home/u918130518/backups/alertcontact`
- **Base de données** : `u918130518_alertcontacts`
- **Surveillance espace** : Alerte si > 80% d'utilisation

#### Configuration des Variables DB
Ajoutez ces variables à votre `.env` pour le backup automatique :
```bash
# Variables pour backup automatique
DB_BACKUP_HOST=localhost
DB_BACKUP_DATABASE=u918130518_alertcontacts
DB_BACKUP_USERNAME=u918130518_alertcontacts
DB_BACKUP_PASSWORD=votre_mot_de_passe_db
```

#### Planification Cron pour Hostinger
Via cPanel > Tâches Cron, ajoutez :
```bash
# Backup quotidien à 3h du matin
0 3 * * * /home/u918130518/domains/alertcontacts.net/public_html/mobile/backup_system.sh

# Vérification espace disque (2 fois par jour)
0 9,21 * * * df /home/u918130518 | awk 'NR==2 {print $5}' | sed 's/%//' | awk '{if($1>85) print "Espace disque critique: "$1"%"}' | mail -s "AlertContact - Espace Disque" admin@alertcontacts.net
```

Créez le fichier `backup_system.sh` :

```bash
#!/bin/bash

# === SYSTÈME DE BACKUP AUTOMATIQUE HOSTINGER ===

# Configuration spécifique Hostinger
PROJECT_PATH="/home/u918130518/domains/alertcontacts.net/public_html/mobile"
BACKUP_PATH="/home/u918130518/backups/alertcontact"
DATE=$(date +%Y%m%d_%H%M%S)

# Variables de base de données (à adapter selon votre configuration)
DB_HOST="localhost"
DB_DATABASE=u918130518_apialertcontac
DB_USERNAME=u918130518_apialertcontac
DB_PASSWORD=vyAQGkC6T> # À définir dans .env ou en variable d'environnement

# Créer le répertoire de backup s'il n'existe pas
mkdir -p "$BACKUP_PATH"
mkdir -p "$BACKUP_PATH/logs_$DATE"

echo "💾 Début du backup Hostinger - $DATE"

# 1. Backup de la configuration
echo "📁 Backup de la configuration..."
tar -czf "$BACKUP_PATH/config_$DATE.tar.gz" \
    "$PROJECT_PATH/.env" \
    "$PROJECT_PATH/config/" \
    "$PROJECT_PATH/routes/console.php" \
    "$PROJECT_PATH/composer.json" \
    "$PROJECT_PATH/composer.lock" 2>/dev/null

# 2. Backup des PIDs et status
echo "🔧 Backup des PIDs et status..."
if [ -d "$PROJECT_PATH/storage/pids" ]; then
    cp -r "$PROJECT_PATH/storage/pids" "$BACKUP_PATH/pids_$DATE" 2>/dev/null || true
fi
cp "$PROJECT_PATH/services_status.json" "$BACKUP_PATH/status_$DATE.json" 2>/dev/null || true

# 3. Backup des logs critiques (derniers 7 jours)
echo "📋 Backup des logs critiques..."
find "$PROJECT_PATH/storage/logs" -name "*.log" -mtime -7 -type f 2>/dev/null | while read logfile; do
    cp "$logfile" "$BACKUP_PATH/logs_$DATE/" 2>/dev/null || true
done

# 4. Backup de la base de données
echo "🗄️ Backup de la base de données..."
if [ -n "$DB_PASSWORD" ]; then
    mysqldump -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" > "$BACKUP_PATH/database_$DATE.sql" 2>/dev/null
    if [ $? -eq 0 ]; then
        echo "✅ Backup DB réussi"
    else
        echo "❌ Erreur backup DB"
    fi
else
    echo "⚠️ Mot de passe DB non défini, backup DB ignoré"
fi

# 5. Backup des fichiers critiques du projet
echo "📦 Backup des fichiers critiques..."
tar -czf "$BACKUP_PATH/app_critical_$DATE.tar.gz" \
    "$PROJECT_PATH/app/Console/Commands/" \
    "$PROJECT_PATH/app/Jobs/" \
    "$PROJECT_PATH/app/Mail/" \
    "$PROJECT_PATH/database/migrations/" 2>/dev/null

# 6. Créer un fichier de métadonnées
echo "📝 Création des métadonnées..."
cat > "$BACKUP_PATH/backup_info_$DATE.txt" << EOF
=== BACKUP ALERTCONTACT HOSTINGER ===
Date: $(date)
Serveur: Hostinger
Projet: $PROJECT_PATH
Backup: $BACKUP_PATH

Fichiers inclus:
- Configuration (.env, config/, routes/console.php)
- PIDs et status des services
- Logs des 7 derniers jours
- Base de données (si configurée)
- Fichiers critiques de l'application

Taille du backup:
$(du -sh "$BACKUP_PATH" 2>/dev/null | cut -f1)
EOF

# 7. Nettoyer les anciens backups (garder 15 jours pour Hostinger)
echo "🧹 Nettoyage des anciens backups..."
find "$BACKUP_PATH" -name "*_20*" -mtime +15 -type f -delete 2>/dev/null || true

# 8. Vérifier l'espace disque disponible
DISK_USAGE=$(df "$BACKUP_PATH" 2>/dev/null | awk 'NR==2 {print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -gt 80 ]; then
    echo "⚠️ Attention: Espace disque à ${DISK_USAGE}%"
fi

echo "✅ Backup terminé : $BACKUP_PATH"
echo "📊 Résumé du backup :"
ls -la "$BACKUP_PATH"/*$DATE* 2>/dev/null || echo "Aucun fichier de backup trouvé"
```

### 2. Sécurisation des Scripts

```bash
# Définir les permissions appropriées
chmod 700 *.sh  # Exécutable par le propriétaire seulement
chmod 600 .env  # Lecture par le propriétaire seulement

# Créer un utilisateur dédié (recommandé)
sudo useradd -m -s /bin/bash alertcontact
sudo usermod -aG www-data alertcontact

# Transférer la propriété
sudo chown -R alertcontact:www-data /path/to/alertcontacts-admin
```

### 3. Monitoring de Sécurité

```bash
# Surveiller les tentatives de connexion
tail -f /var/log/auth.log | grep alertcontact

# Vérifier les processus suspects
ps aux | grep -v grep | grep -E "(artisan|php)" | awk '{print $2, $11}'

# Surveiller l'utilisation des ressources
watch -n 5 'ps aux --sort=-%cpu | head -10'
```

---

## 🚀 Démarrage Rapide - Checklist

### ✅ Installation Initiale

1. **Configurer l'environnement** :
   ```bash
   cp .env.example .env
   # Éditer .env avec les bonnes valeurs
   ```

2. **Créer les scripts** :
   ```bash
   # Copier tous les scripts fournis dans ce guide
   chmod +x *.sh
   ```

3. **Configurer les permissions** :
   ```bash
   chmod -R 755 storage/
   chmod -R 755 bootstrap/cache/
   ```

4. **Tester la configuration** :
   ```bash
   php artisan config:cache
   php artisan migrate:status
   php artisan cleanup:old-data --dry-run
   ```

### ✅ Démarrage des Services

1. **Démarrage complet** :
   ```bash
   ./start_all_services.sh
   ```

2. **Vérification** :
   ```bash
   ./check_services_status.sh
   ```

3. **Configuration du monitoring** :
   ```bash
   crontab -e
   # Ajouter les tâches cron du monitoring
   ```

### ✅ Tests de Fonctionnement

1. **Test du nettoyage** :
   ```bash
   php artisan cleanup:old-data --stats
   php artisan cleanup:old-data --dry-run
   ```

2. **Test des queues** :
   ```bash
   php artisan queue:work --once --verbose
   ```

3. **Test du scheduler** :
   ```bash
   php artisan schedule:run --verbose
   ```

---

## 📞 Support et Contacts

- **Documentation** : Ce fichier
- **Logs principaux** : `storage/logs/`
- **Monitoring** : `./check_services_status.sh`
- **Support technique** : admin@alertcontact.com

---

**Dernière mise à jour** : Novembre 2024  
**Version** : 2.0  
**Responsable** : Équipe DevOps AlertContact

---

> 🎯 **Objectif atteint** : Tous les services backend d'AlertContact fonctionnent de manière persistante et automatique, avec monitoring complet et procédures de récupération.
