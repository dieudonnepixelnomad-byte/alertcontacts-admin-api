#!/bin/bash

# Script de démarrage des jobs Laravel AlertContact
# Usage: ./start_jobs.sh
# Les jobs continuent à fonctionner même après fermeture du terminal SSH

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="$SCRIPT_DIR/storage/logs"
PID_DIR="$SCRIPT_DIR/storage/app/pids"
UNIFIED_LOG="$LOG_DIR/jobs_unified.log"

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction de logging avec timestamp
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$UNIFIED_LOG"
}

# Fonction d'affichage coloré
print_status() {
    echo -e "${2}$1${NC}"
    log_message "$1"
}

# Créer les répertoires nécessaires
mkdir -p "$LOG_DIR" "$PID_DIR"

print_status "🚀 Démarrage des workers Laravel AlertContact..." "$BLUE"
print_status "📁 Répertoire: $SCRIPT_DIR" "$BLUE"
print_status "📋 Log unifié: $UNIFIED_LOG" "$BLUE"

# Vérifier si Laravel est accessible
if ! php artisan --version > /dev/null 2>&1; then
    print_status "❌ Erreur: Laravel n'est pas accessible depuis ce répertoire" "$RED"
    exit 1
fi

# Fonction pour démarrer un worker
start_worker() {
    local queue_name=$1
    local worker_name=$2
    local timeout=${3:-60}
    local tries=${4:-3}
    local sleep=${5:-3}
    local memory=${6:-512}
    
    local pid_file="$PID_DIR/${worker_name}.pid"
    local log_file="$LOG_DIR/${worker_name}.log"
    
    # Vérifier si le worker est déjà en cours d'exécution
    if [ -f "$pid_file" ] && kill -0 "$(cat "$pid_file")" 2>/dev/null; then
        print_status "⚠️  Worker $worker_name déjà en cours d'exécution (PID: $(cat "$pid_file"))" "$YELLOW"
        return 0
    fi
    
    # Supprimer l'ancien fichier PID s'il existe
    [ -f "$pid_file" ] && rm "$pid_file"
    
    print_status "🔄 Démarrage du worker: $worker_name (queue: $queue_name)" "$GREEN"
    
    # Démarrer le worker en arrière-plan avec nohup
    nohup php artisan queue:work \
        --queue="$queue_name" \
        --timeout="$timeout" \
        --tries="$tries" \
        --sleep="$sleep" \
        --memory="$memory" \
        --verbose \
        >> "$log_file" 2>&1 &
    
    local worker_pid=$!
    echo $worker_pid > "$pid_file"
    
    # Attendre un peu pour vérifier que le processus démarre correctement
    sleep 2
    
    if kill -0 "$worker_pid" 2>/dev/null; then
        print_status "✅ Worker $worker_name démarré avec succès (PID: $worker_pid)" "$GREEN"
        log_message "Worker $worker_name - Queue: $queue_name - PID: $worker_pid - Log: $log_file"
    else
        print_status "❌ Échec du démarrage du worker $worker_name" "$RED"
        [ -f "$pid_file" ] && rm "$pid_file"
        return 1
    fi
}

# Démarrer les différents workers selon les queues du projet
print_status "🔧 Configuration des workers..." "$BLUE"

# Worker principal pour la queue par défaut
start_worker "default" "worker_default" 60 3 3 512

# Worker pour le géoprocessing (traitement des positions GPS)
start_worker "geoprocessing" "worker_geoprocessing" 60 3 1 256

# Worker pour les notifications
start_worker "notifications" "worker_notifications" 30 3 1 128

# Worker pour le nettoyage (jobs lourds)
start_worker "cleanup" "worker_cleanup" 7200 1 10 1024

print_status "📊 Résumé des workers démarrés:" "$BLUE"

# Afficher le statut de tous les workers
for pid_file in "$PID_DIR"/*.pid; do
    if [ -f "$pid_file" ]; then
        worker_name=$(basename "$pid_file" .pid)
        pid=$(cat "$pid_file")
        if kill -0 "$pid" 2>/dev/null; then
            print_status "✅ $worker_name (PID: $pid) - ACTIF" "$GREEN"
        else
            print_status "❌ $worker_name (PID: $pid) - INACTIF" "$RED"
            rm "$pid_file"
        fi
    fi
done

print_status "🎯 Commandes utiles:" "$BLUE"
echo "  - Voir le statut: ./status_jobs.sh"
echo "  - Redémarrer: ./restart_jobs.sh"
echo "  - Log unifié: tail -f $UNIFIED_LOG"
echo "  - Logs individuels: tail -f $LOG_DIR/worker_*.log"

print_status "✨ Démarrage terminé! Les workers continuent en arrière-plan." "$GREEN"
log_message "=== Démarrage des jobs terminé ==="