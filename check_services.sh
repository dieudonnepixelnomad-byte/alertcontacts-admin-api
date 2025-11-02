#!/bin/bash

# =============================================================================
# Script de vérification de l'état des services AlertContact
# =============================================================================
# Ce script vérifie l'état de tous les services (jobs, schedules, cleanup)
# et fournit un rapport détaillé de leur fonctionnement
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
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m' # No Color

# Fonction d'affichage avec couleurs
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[⚠]${NC} $1"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1"
}

log_header() {
    echo -e "${BOLD}${CYAN}$1${NC}"
}

# Fonction pour vérifier l'état d'un service
check_service_status() {
    local service_name="$1"
    local pid_file="$2"
    local log_file="$3"
    local expected_process="$4"

    echo ""
    log_header "=== $service_name ==="

    # Vérifier l'existence du fichier PID
    if [[ ! -f "$pid_file" ]]; then
        log_error "Service non démarré (fichier PID absent)"
        echo "  Fichier PID: $pid_file"
        return 1
    fi

    # Lire le PID
    local pid=$(cat "$pid_file" 2>/dev/null)
    if [[ -z "$pid" ]]; then
        log_error "Fichier PID vide ou corrompu"
        echo "  Fichier PID: $pid_file"
        return 1
    fi

    # Vérifier si le processus est actif
    if kill -0 "$pid" 2>/dev/null; then
        log_success "Service actif (PID: $pid)"

        # Obtenir des informations détaillées sur le processus
        local process_info=$(ps -p "$pid" -o pid,ppid,etime,pcpu,pmem,cmd --no-headers 2>/dev/null)
        if [[ -n "$process_info" ]]; then
            echo "  Détails du processus:"
            echo "    PID: $pid"
            echo "    Temps d'exécution: $(echo "$process_info" | awk '{print $3}')"
            echo "    CPU: $(echo "$process_info" | awk '{print $4}')%"
            echo "    Mémoire: $(echo "$process_info" | awk '{print $5}')%"
        fi

        # Vérifier la taille du fichier de log
        if [[ -f "$log_file" ]]; then
            local log_size=$(du -h "$log_file" 2>/dev/null | cut -f1)
            local log_lines=$(wc -l < "$log_file" 2>/dev/null)
            echo "  Log: $log_file ($log_size, $log_lines lignes)"

            # Afficher les dernières lignes du log
            echo "  Dernières activités:"
            tail -n 3 "$log_file" 2>/dev/null | sed 's/^/    /'
        else
            log_warning "Fichier de log non trouvé: $log_file"
        fi

        return 0
    else
        log_error "Service arrêté (PID obsolète: $pid)"
        echo "  Fichier PID: $pid_file"

        # Vérifier si le processus existe sous un autre PID
        if [[ -n "$expected_process" ]]; then
            local running_pids=$(pgrep -f "$expected_process" 2>/dev/null)
            if [[ -n "$running_pids" ]]; then
                log_warning "Processus détecté avec d'autres PIDs: $running_pids"
                echo "  Le fichier PID pourrait être obsolète"
            fi
        fi

        return 1
    fi
}

# Fonction pour vérifier l'état de la base de données
check_database_status() {
    echo ""
    log_header "=== Base de données ==="

    # Tenter une connexion à la base de données
    if php "$PROJECT_DIR/artisan" tinker --execute="DB::connection()->getPdo(); echo 'OK';" 2>/dev/null | grep -q "OK"; then
        log_success "Connexion à la base de données active"

        # Vérifier les jobs en attente
        local pending_jobs=$(php "$PROJECT_DIR/artisan" queue:monitor --once 2>/dev/null | grep -o '[0-9]\+ jobs' | head -1)
        if [[ -n "$pending_jobs" ]]; then
            echo "  Jobs en attente: $pending_jobs"
        else
            echo "  Aucun job en attente"
        fi
    else
        log_error "Impossible de se connecter à la base de données"
        return 1
    fi
}

# Fonction pour vérifier l'espace disque
check_disk_space() {
    echo ""
    log_header "=== Espace disque ==="

    local disk_usage=$(df -h "$PROJECT_DIR" | tail -1 | awk '{print $5}' | sed 's/%//')
    local available_space=$(df -h "$PROJECT_DIR" | tail -1 | awk '{print $4}')

    echo "  Utilisation: ${disk_usage}%"
    echo "  Espace disponible: $available_space"

    if [[ $disk_usage -gt 90 ]]; then
        log_error "Espace disque critique (${disk_usage}%)"
        return 1
    elif [[ $disk_usage -gt 80 ]]; then
        log_warning "Espace disque faible (${disk_usage}%)"
        return 1
    else
        log_success "Espace disque suffisant (${disk_usage}%)"
        return 0
    fi
}

# Fonction pour vérifier les logs d'erreur
check_error_logs() {
    echo ""
    log_header "=== Logs d'erreur récents ==="

    local error_count=0
    local log_files=("$LOG_DIR/scheduler.log" "$LOG_DIR/queue_workers.log" "$LOG_DIR/cleanup.log" "$LOG_DIR/laravel.log")

    for log_file in "${log_files[@]}"; do
        if [[ -f "$log_file" ]]; then
            # Chercher les erreurs dans les dernières 24 heures
            local recent_errors=$(grep -i "error\|exception\|fatal" "$log_file" 2>/dev/null | tail -5)
            if [[ -n "$recent_errors" ]]; then
                echo "  Erreurs dans $(basename "$log_file"):"
                echo "$recent_errors" | sed 's/^/    /'
                ((error_count++))
            fi
        fi
    done

    if [[ $error_count -eq 0 ]]; then
        log_success "Aucune erreur récente détectée"
    else
        log_warning "$error_count fichier(s) de log contiennent des erreurs"
    fi
}

# Fonction pour afficher un résumé des performances
show_performance_summary() {
    echo ""
    log_header "=== Résumé des performances ==="

    # Charge système
    local load_avg=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
    echo "  Charge système: $load_avg"

    # Utilisation mémoire
    local memory_usage=$(free | grep Mem | awk '{printf "%.1f", $3/$2 * 100.0}')
    echo "  Utilisation mémoire: ${memory_usage}%"

    # Nombre de processus PHP actifs
    local php_processes=$(pgrep -c php 2>/dev/null || echo "0")
    echo "  Processus PHP actifs: $php_processes"
}

# Fonction pour générer des recommandations
generate_recommendations() {
    echo ""
    log_header "=== Recommandations ==="

    # Vérifier la taille des logs
    local large_logs=$(find "$LOG_DIR" -name "*.log" -size +100M 2>/dev/null)
    if [[ -n "$large_logs" ]]; then
        log_warning "Logs volumineux détectés (>100MB):"
        echo "$large_logs" | sed 's/^/    /'
        echo "  Recommandation: Archiver ou nettoyer les anciens logs"
    fi

    # Vérifier les fichiers PID obsolètes
    local stale_pids=0
    for pid_file in "$PID_DIR"/*.pid; do
        if [[ -f "$pid_file" ]]; then
            local pid=$(cat "$pid_file" 2>/dev/null)
            if [[ -n "$pid" ]] && ! kill -0 "$pid" 2>/dev/null; then
                ((stale_pids++))
            fi
        fi
    done

    if [[ $stale_pids -gt 0 ]]; then
        log_warning "$stale_pids fichier(s) PID obsolète(s) détecté(s)"
        echo "  Recommandation: Nettoyer les fichiers PID obsolètes"
    fi

    # Recommandations générales
    echo "  Recommandations générales:"
    echo "    - Vérifiez les logs régulièrement"
    echo "    - Surveillez l'espace disque"
    echo "    - Redémarrez les services si nécessaire"
    echo "    - Sauvegardez régulièrement la base de données"
}

# Fonction principale
main() {
    local start_time=$(date +%s)

    echo ""
    log_header "╔══════════════════════════════════════════════════════════════╗"
    log_header "║              VÉRIFICATION DES SERVICES ALERTCONTACT          ║"
    log_header "╚══════════════════════════════════════════════════════════════╝"
    echo ""
    log_info "Début de la vérification: $(date)"

    # Vérifier que nous sommes dans le bon répertoire
    if [[ ! -f "$PROJECT_DIR/artisan" ]]; then
        log_error "Fichier artisan non trouvé dans $PROJECT_DIR"
        log_error "Veuillez vérifier le chemin du projet"
        exit 1
    fi

    # Compteurs pour le résumé
    local services_running=0
    local total_services=4

    # Vérifier chaque service
    if check_service_status "Scheduler Laravel" "$PID_DIR/scheduler.pid" "$LOG_DIR/scheduler.log" "schedule:work"; then
        ((services_running++))
    fi

    if check_service_status "Workers de Queue" "$PID_DIR/queue_workers.pid" "$LOG_DIR/queue_workers.log" "queue:work"; then
        ((services_running++))
    fi

    if check_service_status "Processus de Cleanup" "$PID_DIR/cleanup.pid" "$LOG_DIR/cleanup.log" "cleanup"; then
        ((services_running++))
    fi

    if check_service_status "Monitoring" "$PID_DIR/monitoring.pid" "$LOG_DIR/monitoring.log" "monitoring"; then
        ((services_running++))
    fi

    # Vérifications supplémentaires
    check_database_status
    check_disk_space
    check_error_logs
    show_performance_summary
    generate_recommendations

    # Résumé final
    echo ""
    log_header "╔══════════════════════════════════════════════════════════════╗"
    log_header "║                        RÉSUMÉ FINAL                          ║"
    log_header "╚══════════════════════════════════════════════════════════════╝"

    local end_time=$(date +%s)
    local duration=$((end_time - start_time))

    echo ""
    log_info "Services actifs: $services_running/$total_services"
    log_info "Durée de vérification: ${duration}s"
    log_info "Fin de la vérification: $(date)"

    if [[ $services_running -eq $total_services ]]; then
        echo ""
        log_success "🎉 Tous les services fonctionnent correctement!"
        echo ""
        exit 0
    else
        echo ""
        log_error "⚠️  Certains services ne fonctionnent pas correctement"
        log_info "Utilisez './start_services.sh' pour redémarrer les services"
        echo ""
        exit 1
    fi
}

# Gestion des arguments de ligne de commande
case "${1:-}" in
    --help|-h)
        echo "Usage: $0 [OPTIONS]"
        echo ""
        echo "Options:"
        echo "  --help, -h     Afficher cette aide"
        echo "  --quiet, -q    Mode silencieux (erreurs uniquement)"
        echo "  --json         Sortie au format JSON"
        echo ""
        echo "Exemples:"
        echo "  $0              # Vérification complète"
        echo "  $0 --quiet      # Vérification silencieuse"
        echo "  $0 --json       # Sortie JSON pour intégration"
        exit 0
        ;;
    --quiet|-q)
        # Mode silencieux - rediriger la sortie standard
        exec 1>/dev/null
        ;;
    --json)
        # Mode JSON - à implémenter si nécessaire
        log_info "Mode JSON non encore implémenté"
        exit 1
        ;;
esac

# Exécution du script principal
main "$@"
