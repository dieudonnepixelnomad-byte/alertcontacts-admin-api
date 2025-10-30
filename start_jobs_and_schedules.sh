#!/bin/bash

# Script de démarrage automatique des Jobs et Schedulers - AlertContact
# Usage: ./start_jobs_and_schedules.sh
# Auteur: Assistant IA pour AlertContact
# Date: $(date)

set -e  # Arrêter le script en cas d'erreur

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="$SCRIPT_DIR/storage/logs"
PID_DIR="$SCRIPT_DIR/storage/app/pids"
LARAVEL_LOG="$LOG_DIR/laravel.log"

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Créer les dossiers nécessaires
mkdir -p "$PID_DIR"
mkdir -p "$LOG_DIR"

# Fonction pour afficher les sections
print_section() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}🚀 $1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

# Fonction pour afficher un message de succès
print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

# Fonction pour afficher un message d'erreur
print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Fonction pour afficher un message d'avertissement
print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# Fonction pour afficher un message d'information
print_info() {
    echo -e "${CYAN}ℹ️  $1${NC}"
}

# Fonction pour vérifier si Laravel est accessible
check_laravel() {
    print_info "Vérification de l'accessibilité de Laravel..."
    if php artisan --version >/dev/null 2>&1; then
        print_success "Laravel est accessible"
        return 0
    else
        print_error "Laravel n'est pas accessible. Vérifiez votre installation."
        return 1
    fi
}

# Fonction pour vérifier si un processus est en cours d'exécution
is_process_running() {
    local pid_file="$1"
    local process_name="$2"
    
    if [ -f "$pid_file" ]; then
        local pid=$(cat "$pid_file")
        if ps -p "$pid" > /dev/null 2>&1; then
            # Vérifier que c'est bien le bon processus
            if ps -p "$pid" -o command= | grep -q "$process_name"; then
                return 0  # Processus en cours
            else
                # PID existe mais ce n'est pas le bon processus, nettoyer
                rm -f "$pid_file"
                return 1  # Processus non trouvé
            fi
        else
            # PID n'existe plus, nettoyer
            rm -f "$pid_file"
            return 1  # Processus non trouvé
        fi
    else
        return 1  # Pas de fichier PID
    fi
}

# Fonction pour démarrer le scheduler
start_scheduler() {
    local pid_file="$PID_DIR/scheduler.pid"
    
    print_info "Vérification du scheduler..."
    
    if is_process_running "$pid_file" "schedule:work"; then
        print_warning "Le scheduler est déjà en cours d'exécution (PID: $(cat "$pid_file"))"
        return 0
    fi
    
    print_info "Démarrage du scheduler..."
    nohup php artisan schedule:work > "$LOG_DIR/scheduler.log" 2>&1 &
    local scheduler_pid=$!
    echo "$scheduler_pid" > "$pid_file"
    
    # Vérifier que le processus a bien démarré
    sleep 2
    if ps -p "$scheduler_pid" > /dev/null 2>&1; then
        print_success "Scheduler démarré avec succès (PID: $scheduler_pid)"
        return 0
    else
        print_error "Échec du démarrage du scheduler"
        rm -f "$pid_file"
        return 1
    fi
}

# Fonction pour démarrer un worker de queue
start_queue_worker() {
    local queue_name="$1"
    local pid_file="$PID_DIR/queue_${queue_name}.pid"
    local log_file="$LOG_DIR/queue_${queue_name}.log"
    
    print_info "Vérification du worker pour la queue '$queue_name'..."
    
    if is_process_running "$pid_file" "queue:work.*--queue=$queue_name"; then
        print_warning "Le worker pour la queue '$queue_name' est déjà en cours d'exécution (PID: $(cat "$pid_file"))"
        return 0
    fi
    
    print_info "Démarrage du worker pour la queue '$queue_name'..."
    
    # Paramètres spécifiques selon la queue
    local extra_params=""
    case "$queue_name" in
        "geoprocessing")
            extra_params="--timeout=60 --memory=256 --tries=3"
            ;;
        "notifications")
            extra_params="--timeout=30 --memory=128 --tries=2"
            ;;
        "default")
            extra_params="--timeout=60 --memory=128 --tries=3"
            ;;
    esac
    
    nohup php artisan queue:work --queue="$queue_name" $extra_params --daemon > "$log_file" 2>&1 &
    local worker_pid=$!
    echo "$worker_pid" > "$pid_file"
    
    # Vérifier que le processus a bien démarré
    sleep 2
    if ps -p "$worker_pid" > /dev/null 2>&1; then
        print_success "Worker '$queue_name' démarré avec succès (PID: $worker_pid)"
        return 0
    else
        print_error "Échec du démarrage du worker '$queue_name'"
        rm -f "$pid_file"
        return 1
    fi
}

# Fonction pour afficher le statut des services
show_status() {
    print_section "📊 STATUT DES SERVICES"
    
    # Scheduler
    local scheduler_pid_file="$PID_DIR/scheduler.pid"
    if is_process_running "$scheduler_pid_file" "schedule:work"; then
        print_success "Scheduler: EN COURS (PID: $(cat "$scheduler_pid_file"))"
    else
        print_error "Scheduler: ARRÊTÉ"
    fi
    
    # Workers de queue
    local queues=("default" "geoprocessing" "notifications")
    for queue in "${queues[@]}"; do
        local pid_file="$PID_DIR/queue_${queue}.pid"
        if is_process_running "$pid_file" "queue:work.*--queue=$queue"; then
            print_success "Worker '$queue': EN COURS (PID: $(cat "$pid_file"))"
        else
            print_error "Worker '$queue': ARRÊTÉ"
        fi
    done
}

# Fonction pour nettoyer les anciens PIDs
cleanup_old_pids() {
    print_info "Nettoyage des anciens fichiers PID..."
    
    for pid_file in "$PID_DIR"/*.pid; do
        if [ -f "$pid_file" ]; then
            local pid=$(cat "$pid_file" 2>/dev/null || echo "")
            if [ -n "$pid" ] && ! ps -p "$pid" > /dev/null 2>&1; then
                rm -f "$pid_file"
                print_info "Suppression du PID obsolète: $(basename "$pid_file")"
            fi
        fi
    done
}

# Fonction principale
main() {
    print_section "🚀 DÉMARRAGE DES SERVICES ALERTCONTACT"
    echo -e "${CYAN}📅 $(date)${NC}"
    echo -e "${CYAN}📁 Répertoire: $SCRIPT_DIR${NC}"
    echo ""
    
    # Vérifications préliminaires
    if ! check_laravel; then
        exit 1
    fi
    
    # Nettoyage des anciens PIDs
    cleanup_old_pids
    
    # Démarrage du scheduler
    print_section "⏰ DÉMARRAGE DU SCHEDULER"
    start_scheduler
    
    # Démarrage des workers de queue
    print_section "🔄 DÉMARRAGE DES WORKERS DE QUEUE"
    
    # Queue par défaut
    start_queue_worker "default"
    
    # Queue de géoprocessing (traitement des positions)
    start_queue_worker "geoprocessing"
    
    # Queue de notifications
    start_queue_worker "notifications"
    
    # Affichage du statut final
    echo ""
    show_status
    
    # Informations utiles
    print_section "📋 INFORMATIONS UTILES"
    echo -e "${CYAN}📁 Logs des services:${NC}"
    echo "   • Scheduler: $LOG_DIR/scheduler.log"
    echo "   • Queue default: $LOG_DIR/queue_default.log"
    echo "   • Queue geoprocessing: $LOG_DIR/queue_geoprocessing.log"
    echo "   • Queue notifications: $LOG_DIR/queue_notifications.log"
    echo "   • Laravel: $LARAVEL_LOG"
    echo ""
    echo -e "${CYAN}🔧 Commandes utiles:${NC}"
    echo "   • Vérifier la santé: ./check_jobs_health.sh"
    echo "   • Voir les logs en temps réel: tail -f $LARAVEL_LOG"
    echo "   • Arrêter tous les services: pkill -f 'queue:work|schedule:work'"
    echo "   • Redémarrer les workers: php artisan queue:restart"
    echo ""
    echo -e "${GREEN}✨ Tous les services ont été démarrés avec succès !${NC}"
    echo -e "${CYAN}💡 Utilisez 'ps aux | grep -E \"queue:work|schedule:work\"' pour voir les processus actifs${NC}"
}

# Gestion des signaux pour un arrêt propre
trap 'echo -e "\n${YELLOW}⚠️  Interruption détectée. Les services continuent de fonctionner en arrière-plan.${NC}"; exit 0' INT TERM

# Exécution du script principal
main "$@"