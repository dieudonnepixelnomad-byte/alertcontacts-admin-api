#!/bin/bash

# Script de surveillance des schedules Laravel AlertContact
# Usage: ./monitor_schedules.sh [--watch] [--detailed] [--history] [--test]
# Surveille l'exécution des tâches planifiées et détecte les problèmes

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_DIR="$SCRIPT_DIR/storage/logs"
SCHEDULE_LOG="$LOG_DIR/scheduler.log"
UNIFIED_LOG="$LOG_DIR/jobs_unified.log"
MONITOR_LOG="$LOG_DIR/schedule_monitor.log"

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
WHITE='\033[1;37m'
NC='\033[0m' # No Color

# Options
WATCH_MODE=false
DETAILED_MODE=false
SHOW_HISTORY=false
TEST_MODE=false
REFRESH_INTERVAL=30

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
        --history|-h)
            SHOW_HISTORY=true
            shift
            ;;
        --test|-t)
            TEST_MODE=true
            shift
            ;;
        --interval|-i)
            REFRESH_INTERVAL="$2"
            shift 2
            ;;
        --help)
            echo "Usage: $0 [OPTIONS]"
            echo "Options:"
            echo "  --watch, -w       Mode surveillance continue"
            echo "  --detailed, -d    Affichage détaillé avec logs"
            echo "  --history, -h     Afficher l'historique des exécutions"
            echo "  --test, -t        Tester les schedules manuellement"
            echo "  --interval, -i N  Intervalle de rafraîchissement (défaut: 30s)"
            echo "  --help            Afficher cette aide"
            exit 0
            ;;
        *)
            echo "Option inconnue: $1"
            echo "Utilisez --help pour voir les options disponibles"
            exit 1
            ;;
    esac
done

# Fonction de logging avec timestamp
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$MONITOR_LOG"
}

# Fonction d'affichage coloré
print_status() {
    echo -e "${2}$1${NC}"
    log_message "$1"
}

# Créer les répertoires nécessaires
mkdir -p "$LOG_DIR"

# Définition des schedules attendus (basé sur routes/console.php)
declare -A EXPECTED_SCHEDULES=(
    ["CleanupOldDataJob"]="daily:02:00"
    ["cleanup:cooldowns"]="hourly"
    ["cleanup:tokens"]="every6hours"
    ["telescope:prune"]="every4hours"
    ["cleanup:stats"]="weekly:sunday:06:00"
)

# Fonction pour vérifier si le cron Laravel fonctionne
check_cron_status() {
    print_status "🕐 Vérification du cron Laravel..." "$BLUE"
    
    # Vérifier si le cron principal est configuré
    local cron_check=$(crontab -l 2>/dev/null | grep -c "schedule:run" || echo "0")
    
    if [ "$cron_check" -eq 0 ]; then
        print_status "❌ Aucun cron Laravel détecté dans crontab" "$RED"
        print_status "💡 Ajoutez cette ligne à votre crontab:" "$YELLOW"
        echo "   * * * * * cd $SCRIPT_DIR && php artisan schedule:run >> /dev/null 2>&1"
        return 1
    else
        print_status "✅ Cron Laravel configuré ($cron_check entrée(s))" "$GREEN"
    fi
    
    # Vérifier la dernière exécution du scheduler
    if [ -f "$SCHEDULE_LOG" ]; then
        local last_run=$(tail -1 "$SCHEDULE_LOG" 2>/dev/null | grep -o '\[.*\]' | head -1 | tr -d '[]')
        if [ -n "$last_run" ]; then
            local last_run_timestamp=$(date -j -f "%Y-%m-%d %H:%M:%S" "$last_run" "+%s" 2>/dev/null)
            local current_timestamp=$(date "+%s")
            local time_diff=$((current_timestamp - last_run_timestamp))
            
            if [ $time_diff -lt 120 ]; then  # Moins de 2 minutes
                print_status "✅ Scheduler actif (dernière exécution: il y a ${time_diff}s)" "$GREEN"
            elif [ $time_diff -lt 300 ]; then  # Moins de 5 minutes
                print_status "⚠️  Scheduler possiblement inactif (dernière exécution: il y a ${time_diff}s)" "$YELLOW"
            else
                print_status "❌ Scheduler inactif (dernière exécution: il y a ${time_diff}s)" "$RED"
            fi
        else
            print_status "⚠️  Impossible de déterminer la dernière exécution" "$YELLOW"
        fi
    else
        print_status "⚠️  Aucun log de scheduler trouvé" "$YELLOW"
    fi
}

# Fonction pour analyser les logs de schedule
analyze_schedule_logs() {
    print_status "📊 Analyse des logs de schedules..." "$BLUE"
    
    if [ ! -f "$SCHEDULE_LOG" ]; then
        print_status "⚠️  Fichier de log scheduler non trouvé: $SCHEDULE_LOG" "$YELLOW"
        return 1
    fi
    
    # Analyser les dernières 24 heures
    local yesterday=$(date -v-1d '+%Y-%m-%d' 2>/dev/null || date -d '1 day ago' '+%Y-%m-%d')
    local today=$(date '+%Y-%m-%d')
    
    print_status "📅 Analyse des exécutions (dernières 24h):" "$CYAN"
    
    # Compter les exécutions par type de schedule
    for schedule_name in "${!EXPECTED_SCHEDULES[@]}"; do
        local frequency="${EXPECTED_SCHEDULES[$schedule_name]}"
        local count=$(grep -c "$schedule_name" "$SCHEDULE_LOG" 2>/dev/null || echo "0")
        local recent_count=$(grep "$today\|$yesterday" "$SCHEDULE_LOG" 2>/dev/null | grep -c "$schedule_name" || echo "0")
        
        printf "  %-25s %-15s Total: %-3s Récent: %-3s" "$schedule_name" "($frequency)" "$count" "$recent_count"
        
        # Évaluer si c'est normal
        case "$frequency" in
            "hourly")
                if [ $recent_count -ge 20 ]; then
                    echo -e " ${GREEN}✅${NC}"
                elif [ $recent_count -ge 10 ]; then
                    echo -e " ${YELLOW}⚠️${NC}"
                else
                    echo -e " ${RED}❌${NC}"
                fi
                ;;
            "daily"*|"weekly"*)
                if [ $recent_count -ge 1 ]; then
                    echo -e " ${GREEN}✅${NC}"
                else
                    echo -e " ${RED}❌${NC}"
                fi
                ;;
            "every"*)
                if [ $recent_count -ge 4 ]; then
                    echo -e " ${GREEN}✅${NC}"
                elif [ $recent_count -ge 2 ]; then
                    echo -e " ${YELLOW}⚠️${NC}"
                else
                    echo -e " ${RED}❌${NC}"
                fi
                ;;
            *)
                echo -e " ${CYAN}?${NC}"
                ;;
        esac
    done
}

# Fonction pour afficher l'historique détaillé
show_schedule_history() {
    print_status "📜 Historique des schedules (50 dernières entrées):" "$BLUE"
    
    if [ -f "$SCHEDULE_LOG" ]; then
        tail -50 "$SCHEDULE_LOG" | while IFS= read -r line; do
            if [[ $line == *"Running scheduled command"* ]]; then
                echo -e "${GREEN}▶${NC} $line"
            elif [[ $line == *"Finished scheduled command"* ]]; then
                echo -e "${BLUE}✓${NC} $line"
            elif [[ $line == *"ERROR"* ]] || [[ $line == *"FAILED"* ]]; then
                echo -e "${RED}✗${NC} $line"
            else
                echo -e "${CYAN}•${NC} $line"
            fi
        done
    else
        print_status "⚠️  Aucun historique disponible" "$YELLOW"
    fi
}

# Fonction pour tester les schedules manuellement
test_schedules() {
    print_status "🧪 Test manuel des schedules..." "$BLUE"
    
    # Vérifier si Laravel est accessible
    if ! php artisan --version > /dev/null 2>&1; then
        print_status "❌ Laravel non accessible - Impossible de tester" "$RED"
        return 1
    fi
    
    print_status "🔍 Liste des tâches planifiées:" "$CYAN"
    php artisan schedule:list 2>/dev/null || print_status "  Impossible d'obtenir la liste des schedules" "$YELLOW"
    
    echo ""
    print_status "⚡ Exécution manuelle du scheduler:" "$CYAN"
    php artisan schedule:run --verbose 2>&1 | while IFS= read -r line; do
        if [[ $line == *"Running"* ]]; then
            echo -e "${GREEN}▶${NC} $line"
        elif [[ $line == *"Finished"* ]]; then
            echo -e "${BLUE}✓${NC} $line"
        elif [[ $line == *"No scheduled commands"* ]]; then
            echo -e "${YELLOW}⚠${NC} $line"
        else
            echo -e "${CYAN}•${NC} $line"
        fi
    done
}

# Fonction pour vérifier les jobs en attente liés aux schedules
check_scheduled_jobs() {
    print_status "🔄 Vérification des jobs générés par les schedules..." "$BLUE"
    
    if ! php artisan --version > /dev/null 2>&1; then
        print_status "❌ Laravel non accessible" "$RED"
        return 1
    fi
    
    # Vérifier les jobs en attente
    local pending_jobs=$(php artisan queue:monitor 2>/dev/null | grep -E "(default|cleanup)" | head -5)
    if [ -n "$pending_jobs" ]; then
        print_status "📋 Jobs en attente (liés aux schedules):" "$CYAN"
        echo "$pending_jobs" | sed 's/^/  /'
    else
        print_status "✅ Aucun job en attente lié aux schedules" "$GREEN"
    fi
    
    # Vérifier les jobs échoués récents
    local failed_jobs=$(php artisan queue:failed --format=json 2>/dev/null | jq -r '.[] | select(.failed_at | fromdateiso8601 > (now - 86400)) | .payload' 2>/dev/null | grep -c "CleanupOldDataJob\|SendSafeZoneExitReminders" || echo "0")
    
    if [ "$failed_jobs" -gt 0 ]; then
        print_status "❌ $failed_jobs job(s) de schedule échoué(s) dans les dernières 24h" "$RED"
        if [ "$DETAILED_MODE" = true ]; then
            print_status "Détails des jobs échoués:" "$YELLOW"
            php artisan queue:failed 2>/dev/null | head -10 | sed 's/^/  /'
        fi
    else
        print_status "✅ Aucun job de schedule échoué récemment" "$GREEN"
    fi
}

# Fonction pour surveiller les performances des schedules
monitor_schedule_performance() {
    print_status "⚡ Surveillance des performances:" "$BLUE"
    
    if [ -f "$SCHEDULE_LOG" ]; then
        # Analyser les temps d'exécution
        local avg_cleanup_time=$(grep "CleanupOldDataJob" "$SCHEDULE_LOG" | grep -o "([0-9]*\.[0-9]*s)" | sed 's/[()]//g' | sed 's/s//' | awk '{sum+=$1; count++} END {if(count>0) printf "%.2f", sum/count; else print "N/A"}')
        
        if [ "$avg_cleanup_time" != "N/A" ]; then
            print_status "📊 Temps moyen CleanupOldDataJob: ${avg_cleanup_time}s" "$CYAN"
            
            # Alerter si le temps est anormalement long
            if (( $(echo "$avg_cleanup_time > 300" | bc -l) )); then
                print_status "⚠️  Temps d'exécution élevé détecté!" "$YELLOW"
            fi
        fi
        
        # Vérifier les erreurs récentes
        local recent_errors=$(grep -c "ERROR\|FAILED" "$SCHEDULE_LOG" 2>/dev/null || echo "0")
        if [ "$recent_errors" -gt 0 ]; then
            print_status "❌ $recent_errors erreur(s) détectée(s) dans les logs" "$RED"
        else
            print_status "✅ Aucune erreur récente détectée" "$GREEN"
        fi
    fi
}

# Fonction principale de surveillance
monitor_schedules() {
    # Effacer l'écran en mode watch
    if [ "$WATCH_MODE" = true ]; then
        clear
    fi
    
    # En-tête
    print_status "📅 AlertContact - Surveillance des Schedules" "$MAGENTA"
    print_status "Dernière vérification: $(date '+%Y-%m-%d %H:%M:%S')" "$BLUE"
    print_status "$(printf '%.60s' "$(printf '%*s' 60 '' | tr ' ' '=')")" "$BLUE"
    
    # Vérifications principales
    check_cron_status
    echo ""
    
    analyze_schedule_logs
    echo ""
    
    check_scheduled_jobs
    echo ""
    
    monitor_schedule_performance
    
    # Affichages optionnels
    if [ "$SHOW_HISTORY" = true ]; then
        echo ""
        show_schedule_history
    fi
    
    if [ "$TEST_MODE" = true ]; then
        echo ""
        test_schedules
    fi
    
    # Instructions en bas
    if [ "$WATCH_MODE" = true ]; then
        echo ""
        print_status "🎯 Mode surveillance - Rafraîchissement toutes les ${REFRESH_INTERVAL}s" "$BLUE"
        print_status "Appuyez sur Ctrl+C pour quitter" "$YELLOW"
    else
        echo ""
        print_status "🎯 Commandes utiles:" "$BLUE"
        echo "  - Mode surveillance: ./monitor_schedules.sh --watch"
        echo "  - Avec historique: ./monitor_schedules.sh --history"
        echo "  - Test manuel: ./monitor_schedules.sh --test"
        echo "  - Affichage détaillé: ./monitor_schedules.sh --detailed"
        echo "  - Forcer l'exécution: php artisan schedule:run"
        echo "  - Lister les schedules: php artisan schedule:list"
    fi
}

# Vérifier si Laravel est accessible
if ! php artisan --version > /dev/null 2>&1; then
    print_status "❌ Erreur: Laravel n'est pas accessible depuis ce répertoire" "$RED"
    exit 1
fi

# Mode surveillance ou affichage unique
if [ "$WATCH_MODE" = true ]; then
    # Gérer l'interruption proprement
    trap 'echo -e "\n${YELLOW}Surveillance interrompue${NC}"; exit 0' INT TERM
    
    while true; do
        monitor_schedules
        sleep "$REFRESH_INTERVAL"
    done
else
    monitor_schedules
fi