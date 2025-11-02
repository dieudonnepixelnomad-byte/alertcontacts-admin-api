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
