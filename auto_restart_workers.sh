#!/bin/bash

# Script de surveillance et redémarrage automatique des workers Laravel
# À exécuter via cron toutes les 5 minutes
# Usage: ./auto_restart_workers.sh

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="$SCRIPT_DIR/storage/logs"
PID_DIR="$SCRIPT_DIR/storage/app/pids"
RESTART_LOG="$LOG_DIR/auto_restart.log"

# Créer les répertoires si nécessaire
mkdir -p "$LOG_DIR" "$PID_DIR"

# Fonction de logging
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$RESTART_LOG"
}

# Fonction pour vérifier et redémarrer un worker
check_and_restart_worker() {
    local worker_name=$1
    local pid_file="$PID_DIR/${worker_name}.pid"
    
    # Vérifier si le fichier PID existe
    if [ ! -f "$pid_file" ]; then
        log_message "⚠️  Worker $worker_name: Fichier PID manquant, redémarrage nécessaire"
        return 1
    fi
    
    # Lire le PID
    local pid=$(cat "$pid_file")
    
    # Vérifier si le processus est actif
    if ! kill -0 "$pid" 2>/dev/null; then
        log_message "❌ Worker $worker_name (PID: $pid): Processus arrêté, redémarrage nécessaire"
        rm -f "$pid_file"
        return 1
    fi
    
    # Worker actif
    return 0
}

# Liste des workers à surveiller
WORKERS=("worker_default" "worker_geoprocessing" "worker_notifications" "worker_cleanup")

# Vérifier chaque worker
RESTART_NEEDED=false

for worker in "${WORKERS[@]}"; do
    if ! check_and_restart_worker "$worker"; then
        RESTART_NEEDED=true
    fi
done

# Si au moins un worker est arrêté, redémarrer tous
if [ "$RESTART_NEEDED" = true ]; then
    log_message "🔄 Redémarrage des workers détecté comme nécessaire"
    
    # Arrêter tous les workers existants
    "$SCRIPT_DIR/restart_jobs.sh" >> "$RESTART_LOG" 2>&1
    
    if [ $? -eq 0 ]; then
        log_message "✅ Redémarrage automatique réussi"
        
        # Envoyer une notification (optionnel)
        # curl -X POST "https://your-monitoring-webhook.com" -d "AlertContact workers restarted automatically"
    else
        log_message "❌ Échec du redémarrage automatique"
    fi
else
    # Tous les workers sont actifs, log périodique (une fois par heure)
    MINUTE=$(date +%M)
    if [ "$MINUTE" = "00" ]; then
        log_message "✅ Surveillance: Tous les workers sont actifs"
    fi
fi

# Nettoyer les anciens logs (garder 7 jours)
find "$LOG_DIR" -name "auto_restart.log" -mtime +7 -delete 2>/dev/null

exit 0