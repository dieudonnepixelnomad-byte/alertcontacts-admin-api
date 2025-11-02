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
