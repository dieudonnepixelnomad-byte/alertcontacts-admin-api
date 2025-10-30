#!/bin/bash

# Script d'arrêt des Jobs et Schedulers - AlertContact
# Usage: ./stop_jobs_and_schedules.sh
# Auteur: Assistant IA pour AlertContact

set -e  # Arrêter le script en cas d'erreur

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PID_DIR="$SCRIPT_DIR/storage/app/pids"

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Fonction pour afficher les sections
print_section() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}🛑 $1${NC}"
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

# Fonction pour afficher un message d'information
print_info() {
    echo -e "${CYAN}ℹ️  $1${NC}"
}

# Fonction pour arrêter un processus via son fichier PID
stop_process_by_pid() {
    local pid_file="$1"
    local service_name="$2"
    
    if [ -f "$pid_file" ]; then
        local pid=$(cat "$pid_file")
        print_info "Arrêt de $service_name (PID: $pid)..."
        
        if ps -p "$pid" > /dev/null 2>&1; then
            # Tentative d'arrêt gracieux
            kill -TERM "$pid" 2>/dev/null || true
            
            # Attendre un peu pour l'arrêt gracieux
            sleep 3
            
            # Vérifier si le processus est toujours en cours
            if ps -p "$pid" > /dev/null 2>&1; then
                print_info "Arrêt forcé de $service_name..."
                kill -KILL "$pid" 2>/dev/null || true
                sleep 1
            fi
            
            # Vérifier l'arrêt final
            if ! ps -p "$pid" > /dev/null 2>&1; then
                print_success "$service_name arrêté avec succès"
            else
                print_error "Impossible d'arrêter $service_name"
                return 1
            fi
        else
            print_info "$service_name n'était pas en cours d'exécution"
        fi
        
        # Supprimer le fichier PID
        rm -f "$pid_file"
    else
        print_info "Aucun fichier PID trouvé pour $service_name"
    fi
}

# Fonction pour arrêter tous les processus queue:work et schedule:work
stop_all_laravel_processes() {
    print_info "Recherche de tous les processus Laravel queue:work et schedule:work..."
    
    # Trouver tous les processus queue:work
    local queue_pids=$(pgrep -f "queue:work" 2>/dev/null || true)
    if [ -n "$queue_pids" ]; then
        print_info "Arrêt des processus queue:work trouvés..."
        echo "$queue_pids" | while read -r pid; do
            if [ -n "$pid" ]; then
                print_info "Arrêt du processus queue:work (PID: $pid)..."
                kill -TERM "$pid" 2>/dev/null || true
            fi
        done
        sleep 3
        
        # Vérification et arrêt forcé si nécessaire
        local remaining_queue_pids=$(pgrep -f "queue:work" 2>/dev/null || true)
        if [ -n "$remaining_queue_pids" ]; then
            print_info "Arrêt forcé des processus queue:work restants..."
            echo "$remaining_queue_pids" | while read -r pid; do
                if [ -n "$pid" ]; then
                    kill -KILL "$pid" 2>/dev/null || true
                fi
            done
        fi
    fi
    
    # Trouver tous les processus schedule:work
    local schedule_pids=$(pgrep -f "schedule:work" 2>/dev/null || true)
    if [ -n "$schedule_pids" ]; then
        print_info "Arrêt des processus schedule:work trouvés..."
        echo "$schedule_pids" | while read -r pid; do
            if [ -n "$pid" ]; then
                print_info "Arrêt du processus schedule:work (PID: $pid)..."
                kill -TERM "$pid" 2>/dev/null || true
            fi
        done
        sleep 3
        
        # Vérification et arrêt forcé si nécessaire
        local remaining_schedule_pids=$(pgrep -f "schedule:work" 2>/dev/null || true)
        if [ -n "$remaining_schedule_pids" ]; then
            print_info "Arrêt forcé des processus schedule:work restants..."
            echo "$remaining_schedule_pids" | while read -r pid; do
                if [ -n "$pid" ]; then
                    kill -KILL "$pid" 2>/dev/null || true
                fi
            done
        fi
    fi
}

# Fonction pour nettoyer tous les fichiers PID
cleanup_all_pids() {
    print_info "Nettoyage de tous les fichiers PID..."
    
    if [ -d "$PID_DIR" ]; then
        rm -f "$PID_DIR"/*.pid 2>/dev/null || true
        print_success "Fichiers PID nettoyés"
    fi
}

# Fonction pour afficher le statut final
show_final_status() {
    print_section "📊 STATUT FINAL"
    
    local queue_processes=$(pgrep -f "queue:work" 2>/dev/null | wc -l || echo "0")
    local schedule_processes=$(pgrep -f "schedule:work" 2>/dev/null | wc -l || echo "0")
    
    if [ "$queue_processes" -eq 0 ] && [ "$schedule_processes" -eq 0 ]; then
        print_success "Tous les services ont été arrêtés avec succès"
    else
        print_error "Certains processus sont encore en cours d'exécution:"
        if [ "$queue_processes" -gt 0 ]; then
            print_error "  • $queue_processes processus queue:work"
        fi
        if [ "$schedule_processes" -gt 0 ]; then
            print_error "  • $schedule_processes processus schedule:work"
        fi
    fi
}

# Fonction principale
main() {
    print_section "🛑 ARRÊT DES SERVICES ALERTCONTACT"
    echo -e "${CYAN}📅 $(date)${NC}"
    echo -e "${CYAN}📁 Répertoire: $SCRIPT_DIR${NC}"
    echo ""
    
    # Arrêt via les fichiers PID
    print_section "📋 ARRÊT VIA LES FICHIERS PID"
    
    # Arrêt du scheduler
    stop_process_by_pid "$PID_DIR/scheduler.pid" "Scheduler"
    
    # Arrêt des workers de queue
    local queues=("default" "geoprocessing" "notifications")
    for queue in "${queues[@]}"; do
        stop_process_by_pid "$PID_DIR/queue_${queue}.pid" "Worker '$queue'"
    done
    
    # Arrêt de tous les processus Laravel restants
    print_section "🔍 NETTOYAGE DES PROCESSUS RESTANTS"
    stop_all_laravel_processes
    
    # Nettoyage des fichiers PID
    print_section "🧹 NETTOYAGE"
    cleanup_all_pids
    
    # Statut final
    show_final_status
    
    echo ""
    print_success "Arrêt terminé !"
    echo -e "${CYAN}💡 Pour redémarrer les services: ./start_jobs_and_schedules.sh${NC}"
}

# Exécution du script principal
main "$@"