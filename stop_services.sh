#!/bin/bash

# =============================================================================
# Script d'arrêt des services AlertContact
# =============================================================================
# Ce script arrête proprement tous les services (jobs, schedules, cleanup)
# en utilisant les fichiers PID et en nettoyant les processus orphelins
# =============================================================================

# Configuration
PROJECT_DIR="/home/u123456789/domains/alertcontact.sinestro.fr/public_html"
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

# Fonction pour arrêter un service proprement
stop_service() {
    local service_name="$1"
    local pid_file="$2"
    local process_pattern="$3"
    local force_kill="${4:-false}"
    
    log_info "Arrêt de $service_name..."
    
    # Vérifier si le fichier PID existe
    if [[ ! -f "$pid_file" ]]; then
        log_warning "$service_name: Aucun fichier PID trouvé"
        
        # Chercher les processus correspondants au pattern
        if [[ -n "$process_pattern" ]]; then
            local running_pids=$(pgrep -f "$process_pattern" 2>/dev/null)
            if [[ -n "$running_pids" ]]; then
                log_warning "Processus $service_name détectés sans fichier PID: $running_pids"
                for pid in $running_pids; do
                    if kill -TERM "$pid" 2>/dev/null; then
                        log_info "Signal TERM envoyé au processus $pid"
                        sleep 2
                        if kill -0 "$pid" 2>/dev/null; then
                            if [[ "$force_kill" == "true" ]]; then
                                kill -KILL "$pid" 2>/dev/null
                                log_warning "Processus $pid forcé à s'arrêter (KILL)"
                            fi
                        else
                            log_success "Processus $pid arrêté proprement"
                        fi
                    fi
                done
            else
                log_success "$service_name: Aucun processus en cours d'exécution"
            fi
        fi
        return 0
    fi
    
    # Lire le PID
    local pid=$(cat "$pid_file" 2>/dev/null)
    if [[ -z "$pid" ]]; then
        log_error "$service_name: Fichier PID vide ou corrompu"
        rm -f "$pid_file"
        return 1
    fi
    
    # Vérifier si le processus existe
    if ! kill -0 "$pid" 2>/dev/null; then
        log_warning "$service_name: Processus déjà arrêté (PID: $pid)"
        rm -f "$pid_file"
        return 0
    fi
    
    # Tentative d'arrêt gracieux avec TERM
    log_info "$service_name: Envoi du signal TERM au processus $pid"
    if kill -TERM "$pid" 2>/dev/null; then
        # Attendre jusqu'à 10 secondes pour un arrêt gracieux
        local count=0
        while [[ $count -lt 10 ]] && kill -0 "$pid" 2>/dev/null; do
            sleep 1
            ((count++))
            echo -n "."
        done
        echo ""
        
        # Vérifier si le processus s'est arrêté
        if kill -0 "$pid" 2>/dev/null; then
            if [[ "$force_kill" == "true" ]]; then
                log_warning "$service_name: Arrêt forcé nécessaire (KILL)"
                kill -KILL "$pid" 2>/dev/null
                sleep 1
                
                if kill -0 "$pid" 2>/dev/null; then
                    log_error "$service_name: Impossible d'arrêter le processus $pid"
                    return 1
                else
                    log_success "$service_name: Processus arrêté de force (PID: $pid)"
                fi
            else
                log_warning "$service_name: Le processus ne répond pas au signal TERM"
                log_info "Utilisez l'option --force pour un arrêt forcé"
                return 1
            fi
        else
            log_success "$service_name: Processus arrêté proprement (PID: $pid)"
        fi
    else
        log_error "$service_name: Impossible d'envoyer le signal au processus $pid"
        return 1
    fi
    
    # Supprimer le fichier PID
    rm -f "$pid_file"
    return 0
}

# Fonction pour nettoyer les processus orphelins
cleanup_orphan_processes() {
    log_info "Nettoyage des processus orphelins..."
    
    local patterns=(
        "artisan schedule:work"
        "artisan queue:work"
        "artisan app:cleanup-old-data"
        "monitoring"
    )
    
    local orphans_found=0
    
    for pattern in "${patterns[@]}"; do
        local orphan_pids=$(pgrep -f "$pattern" 2>/dev/null)
        if [[ -n "$orphan_pids" ]]; then
            log_warning "Processus orphelins détectés pour '$pattern': $orphan_pids"
            for pid in $orphan_pids; do
                if kill -TERM "$pid" 2>/dev/null; then
                    log_info "Signal TERM envoyé au processus orphelin $pid"
                    ((orphans_found++))
                fi
            done
        fi
    done
    
    if [[ $orphans_found -gt 0 ]]; then
        log_info "Attente de l'arrêt des processus orphelins..."
        sleep 3
        
        # Vérification finale et arrêt forcé si nécessaire
        for pattern in "${patterns[@]}"; do
            local remaining_pids=$(pgrep -f "$pattern" 2>/dev/null)
            if [[ -n "$remaining_pids" ]]; then
                log_warning "Arrêt forcé des processus restants: $remaining_pids"
                for pid in $remaining_pids; do
                    kill -KILL "$pid" 2>/dev/null
                done
            fi
        done
        
        log_success "Nettoyage des processus orphelins terminé"
    else
        log_success "Aucun processus orphelin détecté"
    fi
}

# Fonction pour nettoyer les fichiers temporaires
cleanup_temp_files() {
    log_info "Nettoyage des fichiers temporaires..."
    
    # Nettoyer les fichiers PID obsolètes
    local stale_pids=0
    if [[ -d "$PID_DIR" ]]; then
        for pid_file in "$PID_DIR"/*.pid; do
            if [[ -f "$pid_file" ]]; then
                local pid=$(cat "$pid_file" 2>/dev/null)
                if [[ -n "$pid" ]] && ! kill -0 "$pid" 2>/dev/null; then
                    log_info "Suppression du fichier PID obsolète: $(basename "$pid_file")"
                    rm -f "$pid_file"
                    ((stale_pids++))
                fi
            fi
        done
    fi
    
    # Nettoyer les fichiers de lock Laravel
    local lock_files=$(find "$PROJECT_DIR/storage/framework" -name "*.lock" 2>/dev/null)
    if [[ -n "$lock_files" ]]; then
        log_info "Suppression des fichiers de lock Laravel..."
        echo "$lock_files" | xargs rm -f
    fi
    
    # Nettoyer le cache des sessions
    if [[ -d "$PROJECT_DIR/storage/framework/sessions" ]]; then
        local old_sessions=$(find "$PROJECT_DIR/storage/framework/sessions" -type f -mtime +1 2>/dev/null)
        if [[ -n "$old_sessions" ]]; then
            log_info "Suppression des anciennes sessions..."
            echo "$old_sessions" | xargs rm -f
        fi
    fi
    
    log_success "Nettoyage terminé ($stale_pids fichiers PID obsolètes supprimés)"
}

# Fonction pour afficher un résumé des services arrêtés
show_stop_summary() {
    echo ""
    log_header "=== Résumé de l'arrêt ==="
    
    # Vérifier qu'aucun service n'est encore actif
    local remaining_processes=0
    local services=("scheduler" "queue_workers" "cleanup" "monitoring")
    
    for service in "${services[@]}"; do
        local pid_file="$PID_DIR/${service}.pid"
        if [[ -f "$pid_file" ]]; then
            local pid=$(cat "$pid_file" 2>/dev/null)
            if [[ -n "$pid" ]] && kill -0 "$pid" 2>/dev/null; then
                log_warning "Service encore actif: $service (PID: $pid)"
                ((remaining_processes++))
            fi
        fi
    done
    
    # Vérifier les processus Laravel restants
    local laravel_processes=$(pgrep -f "artisan" 2>/dev/null | wc -l)
    
    echo ""
    if [[ $remaining_processes -eq 0 ]] && [[ $laravel_processes -eq 0 ]]; then
        log_success "🎉 Tous les services ont été arrêtés avec succès!"
        log_info "Aucun processus AlertContact en cours d'exécution"
    else
        log_warning "⚠️  Certains processus peuvent encore être actifs"
        log_info "Processus Laravel restants: $laravel_processes"
        log_info "Utilisez 'ps aux | grep artisan' pour vérifier manuellement"
    fi
}

# Fonction principale
main() {
    local force_kill=false
    local cleanup_only=false
    
    # Traitement des arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --force|-f)
                force_kill=true
                shift
                ;;
            --cleanup-only|-c)
                cleanup_only=true
                shift
                ;;
            --help|-h)
                echo "Usage: $0 [OPTIONS]"
                echo ""
                echo "Options:"
                echo "  --force, -f        Arrêt forcé des processus (SIGKILL)"
                echo "  --cleanup-only, -c Nettoyer uniquement les fichiers temporaires"
                echo "  --help, -h         Afficher cette aide"
                echo ""
                echo "Exemples:"
                echo "  $0                 # Arrêt normal de tous les services"
                echo "  $0 --force         # Arrêt forcé de tous les services"
                echo "  $0 --cleanup-only  # Nettoyer uniquement les fichiers temporaires"
                exit 0
                ;;
            *)
                log_error "Option inconnue: $1"
                log_info "Utilisez --help pour voir les options disponibles"
                exit 1
                ;;
        esac
    done
    
    local start_time=$(date +%s)
    
    echo ""
    log_header "╔══════════════════════════════════════════════════════════════╗"
    log_header "║                ARRÊT DES SERVICES ALERTCONTACT               ║"
    log_header "╚══════════════════════════════════════════════════════════════╝"
    echo ""
    log_info "Début de l'arrêt: $(date)"
    
    if [[ "$force_kill" == "true" ]]; then
        log_warning "Mode arrêt forcé activé (SIGKILL)"
    fi
    
    # Vérifier que nous sommes dans le bon répertoire
    if [[ ! -f "$PROJECT_DIR/artisan" ]]; then
        log_error "Fichier artisan non trouvé dans $PROJECT_DIR"
        log_error "Veuillez vérifier le chemin du projet"
        exit 1
    fi
    
    if [[ "$cleanup_only" == "true" ]]; then
        cleanup_temp_files
        exit 0
    fi
    
    # Arrêter tous les services dans l'ordre inverse du démarrage
    local services_stopped=0
    local total_services=4
    
    echo ""
    log_info "Arrêt des services en cours..."
    
    # 1. Arrêter le monitoring en premier
    if stop_service "Monitoring" "$PID_DIR/monitoring.pid" "monitoring" "$force_kill"; then
        ((services_stopped++))
    fi
    
    # 2. Arrêter le processus de cleanup
    if stop_service "Processus de Cleanup" "$PID_DIR/cleanup.pid" "app:cleanup-old-data" "$force_kill"; then
        ((services_stopped++))
    fi
    
    # 3. Arrêter les workers de queue
    if stop_service "Workers de Queue" "$PID_DIR/queue_workers.pid" "queue:work" "$force_kill"; then
        ((services_stopped++))
    fi
    
    # 4. Arrêter le scheduler en dernier
    if stop_service "Scheduler Laravel" "$PID_DIR/scheduler.pid" "schedule:work" "$force_kill"; then
        ((services_stopped++))
    fi
    
    echo ""
    log_info "Services traités: $services_stopped/$total_services"
    
    # Nettoyer les processus orphelins
    cleanup_orphan_processes
    
    # Nettoyer les fichiers temporaires
    cleanup_temp_files
    
    # Afficher le résumé
    show_stop_summary
    
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))
    
    echo ""
    log_info "Durée de l'arrêt: ${duration}s"
    log_info "Fin de l'arrêt: $(date)"
    echo ""
    
    # Code de sortie basé sur le succès de l'arrêt
    if [[ $services_stopped -eq $total_services ]]; then
        exit 0
    else
        exit 1
    fi
}

# Gestion des signaux pour un arrêt propre du script
trap 'log_warning "Arrêt du script interrompu par signal"; exit 130' INT TERM

# Exécution du script principal
main "$@"