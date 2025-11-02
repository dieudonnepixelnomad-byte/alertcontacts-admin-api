#!/bin/bash

# =============================================================================
# Script de démarrage des services AlertContact
# =============================================================================
# Ce script démarre tous les services nécessaires (jobs, schedules, cleanup)
# de manière persistante même après fermeture du terminal SSH
# =============================================================================

# Configuration
PROJECT_DIR="/home/u918130518/domains/alertcontacts.net/public_html/mobile"
LOG_DIR="$PROJECT_DIR/storage/logs"
PID_DIR="$PROJECT_DIR/storage/app/pids"

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction d'affichage avec couleurs
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Fonction pour vérifier si un processus est actif
is_process_running() {
    local pid_file="$1"
    local service_name="$2"

    if [[ -f "$pid_file" ]]; then
        local pid=$(cat "$pid_file")
        if kill -0 "$pid" 2>/dev/null; then
            log_warning "$service_name est déjà actif (PID: $pid)"
            return 0
        else
            log_info "Fichier PID obsolète détecté pour $service_name, suppression..."
            rm -f "$pid_file"
            return 1
        fi
    else
        return 1
    fi
}

# Fonction pour démarrer le scheduler Laravel
start_scheduler() {
    local pid_file="$PID_DIR/scheduler.pid"
    local log_file="$LOG_DIR/scheduler.log"

    log_info "Démarrage du scheduler Laravel..."

    if is_process_running "$pid_file" "Scheduler Laravel"; then
        return 0
    fi

    # Démarrage du scheduler en arrière-plan avec persistance SSH
    nohup php "$PROJECT_DIR/artisan" schedule:work \
        --sleep=60 \
        >> "$log_file" 2>&1 &

    local scheduler_pid=$!
    echo $scheduler_pid > "$pid_file"

    # Détacher le processus du terminal
    disown $scheduler_pid

    sleep 2

    if kill -0 $scheduler_pid 2>/dev/null; then
        log_success "Scheduler Laravel démarré (PID: $scheduler_pid)"
        return 0
    else
        log_error "Échec du démarrage du scheduler Laravel"
        rm -f "$pid_file"
        return 1
    fi
}

# Fonction pour démarrer les workers de queue
start_queue_workers() {
    local pid_file="$PID_DIR/queue_workers.pid"
    local log_file="$LOG_DIR/queue_workers.log"

    log_info "Démarrage des workers de queue..."

    if is_process_running "$pid_file" "Workers de queue"; then
        return 0
    fi

    # Démarrage des workers avec persistance SSH
    nohup php "$PROJECT_DIR/artisan" queue:work \
        --queue=default,high,low \
        --sleep=3 \
        --tries=3 \
        --max-time=3600 \
        --memory=512 \
        >> "$log_file" 2>&1 &

    local worker_pid=$!
    echo $worker_pid > "$pid_file"

    # Détacher le processus du terminal
    disown $worker_pid

    sleep 2

    if kill -0 $worker_pid 2>/dev/null; then
        log_success "Workers de queue démarrés (PID: $worker_pid)"
        return 0
    else
        log_error "Échec du démarrage des workers de queue"
        rm -f "$pid_file"
        return 1
    fi
}

# Fonction pour démarrer le processus de cleanup
start_cleanup_process() {
    local pid_file="$PID_DIR/cleanup.pid"
    local log_file="$LOG_DIR/cleanup.log"

    log_info "Démarrage du processus de cleanup..."

    if is_process_running "$pid_file" "Processus de cleanup"; then
        return 0
    fi

    # Script de cleanup qui s'exécute en boucle
    nohup bash -c "
        while true; do
            echo \"[$(date)] Exécution du cleanup automatique...\" >> \"$log_file\"
            php \"$PROJECT_DIR/artisan\" app:cleanup-old-data >> \"$log_file\" 2>&1

            # Attendre 1 heure avant la prochaine exécution
            sleep 3600
        done
    " &

    local cleanup_pid=$!
    echo $cleanup_pid > "$pid_file"

    # Détacher le processus du terminal
    disown $cleanup_pid

    sleep 2

    if kill -0 $cleanup_pid 2>/dev/null; then
        log_success "Processus de cleanup démarré (PID: $cleanup_pid)"
        return 0
    else
        log_error "Échec du démarrage du processus de cleanup"
        rm -f "$pid_file"
        return 1
    fi
}

# Fonction pour démarrer le monitoring
start_monitoring() {
    local pid_file="$PID_DIR/monitoring.pid"
    local log_file="$LOG_DIR/monitoring.log"

    log_info "Démarrage du monitoring des services..."

    if is_process_running "$pid_file" "Monitoring"; then
        return 0
    fi

    # Script de monitoring qui vérifie les services toutes les 5 minutes
    nohup bash -c "
        while true; do
            echo \"[$(date)] Vérification des services...\" >> \"$log_file\"

            # Vérifier si les services sont toujours actifs
            bash \"$PROJECT_DIR/check_services.sh\" >> \"$log_file\" 2>&1

            # Attendre 5 minutes avant la prochaine vérification
            sleep 300
        done
    " &

    local monitoring_pid=$!
    echo $monitoring_pid > "$pid_file"

    # Détacher le processus du terminal
    disown $monitoring_pid

    sleep 2

    if kill -0 $monitoring_pid 2>/dev/null; then
        log_success "Monitoring démarré (PID: $monitoring_pid)"
        return 0
    else
        log_error "Échec du démarrage du monitoring"
        rm -f "$pid_file"
        return 1
    fi
}

# Fonction principale
main() {
    log_info "=== Démarrage des services AlertContact ==="

    # Vérifier que nous sommes dans le bon répertoire
    if [[ ! -f "$PROJECT_DIR/artisan" ]]; then
        log_error "Fichier artisan non trouvé dans $PROJECT_DIR"
        log_error "Veuillez vérifier le chemin du projet"
        exit 1
    fi

    # Créer les répertoires nécessaires
    mkdir -p "$LOG_DIR" "$PID_DIR"

    # Démarrer tous les services
    local services_started=0
    local total_services=4

    if start_scheduler; then
        ((services_started++))
    fi

    if start_queue_workers; then
        ((services_started++))
    fi

    if start_cleanup_process; then
        ((services_started++))
    fi

    if start_monitoring; then
        ((services_started++))
    fi

    # Résumé final
    echo ""
    log_info "=== Résumé du démarrage ==="
    log_info "Services démarrés: $services_started/$total_services"

    if [[ $services_started -eq $total_services ]]; then
        log_success "Tous les services ont été démarrés avec succès!"
        log_info "Les services continueront de fonctionner même après fermeture du terminal SSH"
        log_info "Utilisez './check_services.sh' pour vérifier l'état des services"
    else
        log_warning "Certains services n'ont pas pu être démarrés"
        log_info "Vérifiez les logs dans $LOG_DIR pour plus de détails"
    fi

    echo ""
    log_info "Fichiers de logs disponibles:"
    log_info "  - Scheduler: $LOG_DIR/scheduler.log"
    log_info "  - Queue Workers: $LOG_DIR/queue_workers.log"
    log_info "  - Cleanup: $LOG_DIR/cleanup.log"
    log_info "  - Monitoring: $LOG_DIR/monitoring.log"
}

# Exécution du script principal
main "$@"
