#!/bin/bash

# Script de surveillance des jobs Laravel AlertContact
# Usage: ./status_jobs.sh [--watch] [--detailed] [--logs]
# Affiche l'état des workers et des statistiques des jobs

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
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m' # No Color

# Options
WATCH_MODE=false
DETAILED_MODE=false
SHOW_LOGS=false
REFRESH_INTERVAL=5

# Parser les arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --watch|-w)
            WATCH_MODE=true
            shift
            ;;
        --detailed|-d)
            DETAILED_MODE=true
            shift
            ;;
        --logs|-l)
            SHOW_LOGS=true
            shift
            ;;
        --interval|-i)
            REFRESH_INTERVAL="$2"
            shift 2
            ;;
        --help|-h)
            echo "Usage: $0 [OPTIONS]"
            echo "Options:"
            echo "  --watch, -w       Mode surveillance continue"
            echo "  --detailed, -d    Affichage détaillé"
            echo "  --logs, -l        Afficher les dernières lignes de logs"
            echo "  --interval, -i N  Intervalle de rafraîchissement (défaut: 5s)"
            echo "  --help, -h        Afficher cette aide"
            exit 0
            ;;
        *)
            echo "Option inconnue: $1"
            echo "Utilisez --help pour voir les options disponibles"
            exit 1
            ;;
    esac
done

# Fonction d'affichage coloré
print_status() {
    echo -e "${2}$1${NC}"
}

# Fonction pour obtenir l'uptime d'un processus
get_process_uptime() {
    local pid=$1
    if [ -z "$pid" ] || ! kill -0 "$pid" 2>/dev/null; then
        echo "N/A"
        return
    fi
    
    # Obtenir le temps de démarrage du processus (en secondes depuis epoch)
    if command -v ps >/dev/null 2>&1; then
        local start_time=$(ps -o lstart= -p "$pid" 2>/dev/null | head -1)
        if [ -n "$start_time" ]; then
            local start_epoch=$(date -j -f "%a %b %d %H:%M:%S %Y" "$start_time" "+%s" 2>/dev/null)
            if [ -n "$start_epoch" ]; then
                local current_epoch=$(date "+%s")
                local uptime_seconds=$((current_epoch - start_epoch))
                
                # Convertir en format lisible
                local days=$((uptime_seconds / 86400))
                local hours=$(((uptime_seconds % 86400) / 3600))
                local minutes=$(((uptime_seconds % 3600) / 60))
                
                if [ $days -gt 0 ]; then
                    echo "${days}j ${hours}h ${minutes}m"
                elif [ $hours -gt 0 ]; then
                    echo "${hours}h ${minutes}m"
                else
                    echo "${minutes}m"
                fi
                return
            fi
        fi
    fi
    
    echo "N/A"
}

# Fonction pour obtenir l'utilisation mémoire d'un processus
get_memory_usage() {
    local pid=$1
    if [ -z "$pid" ] || ! kill -0 "$pid" 2>/dev/null; then
        echo "N/A"
        return
    fi
    
    if command -v ps >/dev/null 2>&1; then
        local memory=$(ps -o rss= -p "$pid" 2>/dev/null | tr -d ' ')
        if [ -n "$memory" ] && [ "$memory" -gt 0 ]; then
            # Convertir de KB en MB
            local memory_mb=$((memory / 1024))
            echo "${memory_mb}MB"
        else
            echo "N/A"
        fi
    else
        echo "N/A"
    fi
}

# Fonction pour afficher l'état des workers
show_workers_status() {
    print_status "🔧 État des Workers" "$BLUE"
    print_status "===================" "$BLUE"
    
    local total_workers=0
    local active_workers=0
    local inactive_workers=0
    
    # En-tête du tableau
    if [ "$DETAILED_MODE" = true ]; then
        printf "%-20s %-8s %-12s %-10s %-8s %-15s\n" "WORKER" "STATUS" "PID" "UPTIME" "MEMORY" "LOG FILE"
        print_status "$(printf '%.80s' "$(printf '%*s' 80 '' | tr ' ' '-')")" "$CYAN"
    else
        printf "%-20s %-8s %-12s %-10s\n" "WORKER" "STATUS" "PID" "UPTIME"
        print_status "$(printf '%.50s' "$(printf '%*s' 50 '' | tr ' ' '-')")" "$CYAN"
    fi
    
    # Parcourir tous les fichiers PID
    for pid_file in "$PID_DIR"/*.pid; do
        if [ -f "$pid_file" ]; then
            total_workers=$((total_workers + 1))
            worker_name=$(basename "$pid_file" .pid)
            pid=$(cat "$pid_file" 2>/dev/null)
            
            if [ -n "$pid" ] && kill -0 "$pid" 2>/dev/null; then
                active_workers=$((active_workers + 1))
                uptime=$(get_process_uptime "$pid")
                memory=$(get_memory_usage "$pid")
                
                if [ "$DETAILED_MODE" = true ]; then
                    log_file="$LOG_DIR/${worker_name}.log"
                    printf "%-20s ${GREEN}%-8s${NC} %-12s %-10s %-8s %-15s\n" \
                        "$worker_name" "ACTIF" "$pid" "$uptime" "$memory" "$(basename "$log_file")"
                else
                    printf "%-20s ${GREEN}%-8s${NC} %-12s %-10s\n" \
                        "$worker_name" "ACTIF" "$pid" "$uptime"
                fi
            else
                inactive_workers=$((inactive_workers + 1))
                if [ "$DETAILED_MODE" = true ]; then
                    printf "%-20s ${RED}%-8s${NC} %-12s %-10s %-8s %-15s\n" \
                        "$worker_name" "INACTIF" "N/A" "N/A" "N/A" "N/A"
                else
                    printf "%-20s ${RED}%-8s${NC} %-12s %-10s\n" \
                        "$worker_name" "INACTIF" "N/A" "N/A"
                fi
            fi
        fi
    done
    
    if [ $total_workers -eq 0 ]; then
        print_status "⚠️  Aucun worker configuré" "$YELLOW"
    else
        echo ""
        print_status "📊 Résumé: $active_workers actifs, $inactive_workers inactifs sur $total_workers total" "$CYAN"
    fi
}

# Fonction pour afficher les statistiques des jobs
show_jobs_statistics() {
    print_status "" ""
    print_status "📈 Statistiques des Jobs" "$BLUE"
    print_status "========================" "$BLUE"
    
    # Vérifier si Laravel est accessible
    if ! php artisan --version > /dev/null 2>&1; then
        print_status "❌ Laravel non accessible - Impossible d'obtenir les statistiques" "$RED"
        return
    fi
    
    # Jobs en attente par queue
    print_status "🔄 Jobs en attente par queue:" "$CYAN"
    php artisan queue:monitor 2>/dev/null | head -10 || print_status "  Aucune information disponible" "$YELLOW"
    
    echo ""
    
    # Jobs échoués
    local failed_jobs=$(php artisan queue:failed --format=json 2>/dev/null | jq length 2>/dev/null || echo "N/A")
    if [ "$failed_jobs" != "N/A" ] && [ "$failed_jobs" -gt 0 ]; then
        print_status "❌ Jobs échoués: $failed_jobs" "$RED"
        if [ "$DETAILED_MODE" = true ]; then
            print_status "Derniers jobs échoués:" "$YELLOW"
            php artisan queue:failed 2>/dev/null | head -5
        fi
    else
        print_status "✅ Aucun job échoué" "$GREEN"
    fi
    
    echo ""
    
    # Statistiques des cooldowns (spécifique à AlertContact)
    print_status "❄️  Statistiques des cooldowns:" "$CYAN"
    php artisan cooldown:manage stats 2>/dev/null || print_status "  Impossible d'obtenir les statistiques des cooldowns" "$YELLOW"
}

# Fonction pour afficher les logs récents
show_recent_logs() {
    print_status "" ""
    print_status "📋 Logs Récents" "$BLUE"
    print_status "===============" "$BLUE"
    
    # Log unifié
    if [ -f "$UNIFIED_LOG" ]; then
        print_status "📄 Log unifié (10 dernières lignes):" "$CYAN"
        tail -10 "$UNIFIED_LOG" 2>/dev/null || print_status "  Impossible de lire le log unifié" "$YELLOW"
    else
        print_status "⚠️  Log unifié non trouvé: $UNIFIED_LOG" "$YELLOW"
    fi
    
    echo ""
    
    # Logs des workers individuels
    if [ "$DETAILED_MODE" = true ]; then
        for log_file in "$LOG_DIR"/worker_*.log; do
            if [ -f "$log_file" ]; then
                worker_name=$(basename "$log_file" .log)
                print_status "📄 $worker_name (5 dernières lignes):" "$CYAN"
                tail -5 "$log_file" 2>/dev/null | sed 's/^/  /' || print_status "  Impossible de lire le log" "$YELLOW"
                echo ""
            fi
        done
    fi
    
    # Log Laravel principal
    local laravel_log="$LOG_DIR/laravel.log"
    if [ -f "$laravel_log" ]; then
        print_status "📄 Laravel (5 dernières lignes):" "$CYAN"
        tail -5 "$laravel_log" 2>/dev/null | sed 's/^/  /' || print_status "  Impossible de lire le log Laravel" "$YELLOW"
    fi
}

# Fonction principale d'affichage du statut
show_status() {
    # Effacer l'écran en mode watch
    if [ "$WATCH_MODE" = true ]; then
        clear
    fi
    
    # En-tête
    print_status "🚀 AlertContact - Statut des Jobs" "$MAGENTA"
    print_status "Dernière mise à jour: $(date '+%Y-%m-%d %H:%M:%S')" "$BLUE"
    print_status "$(printf '%.50s' "$(printf '%*s' 50 '' | tr ' ' '=')")" "$BLUE"
    
    # Afficher l'état des workers
    show_workers_status
    
    # Afficher les statistiques des jobs
    show_jobs_statistics
    
    # Afficher les logs si demandé
    if [ "$SHOW_LOGS" = true ]; then
        show_recent_logs
    fi
    
    # Instructions en bas
    if [ "$WATCH_MODE" = true ]; then
        echo ""
        print_status "🎯 Mode surveillance - Rafraîchissement toutes les ${REFRESH_INTERVAL}s" "$BLUE"
        print_status "Appuyez sur Ctrl+C pour quitter" "$YELLOW"
    else
        echo ""
        print_status "🎯 Commandes utiles:" "$BLUE"
        echo "  - Mode surveillance: ./status_jobs.sh --watch"
        echo "  - Affichage détaillé: ./status_jobs.sh --detailed"
        echo "  - Avec logs: ./status_jobs.sh --logs"
        echo "  - Démarrer jobs: ./start_jobs.sh"
        echo "  - Redémarrer jobs: ./restart_jobs.sh"
    fi
}

# Créer les répertoires nécessaires
mkdir -p "$LOG_DIR" "$PID_DIR"

# Mode surveillance ou affichage unique
if [ "$WATCH_MODE" = true ]; then
    # Gérer l'interruption proprement
    trap 'echo -e "\n${YELLOW}Surveillance interrompue${NC}"; exit 0' INT TERM
    
    while true; do
        show_status
        sleep "$REFRESH_INTERVAL"
    done
else
    show_status
fi