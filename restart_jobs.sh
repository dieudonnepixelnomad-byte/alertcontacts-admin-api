#!/bin/bash

# Script de redémarrage des jobs Laravel AlertContact
# Usage: ./restart_jobs.sh
# Arrête proprement tous les workers et les redémarre

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

print_status "🔄 Redémarrage des workers Laravel AlertContact..." "$BLUE"
print_status "📁 Répertoire: $SCRIPT_DIR" "$BLUE"

# Fonction pour arrêter un worker proprement
stop_worker() {
    local worker_name=$1
    local pid_file="$PID_DIR/${worker_name}.pid"
    
    if [ ! -f "$pid_file" ]; then
        print_status "⚠️  Aucun fichier PID trouvé pour $worker_name" "$YELLOW"
        return 0
    fi
    
    local pid=$(cat "$pid_file")
    
    if ! kill -0 "$pid" 2>/dev/null; then
        print_status "⚠️  Worker $worker_name (PID: $pid) n'est pas en cours d'exécution" "$YELLOW"
        rm "$pid_file"
        return 0
    fi
    
    print_status "🛑 Arrêt du worker $worker_name (PID: $pid)..." "$YELLOW"
    
    # Tentative d'arrêt gracieux avec SIGTERM
    kill -TERM "$pid" 2>/dev/null
    
    # Attendre jusqu'à 30 secondes pour l'arrêt gracieux
    local count=0
    while kill -0 "$pid" 2>/dev/null && [ $count -lt 30 ]; do
        sleep 1
        count=$((count + 1))
        if [ $((count % 5)) -eq 0 ]; then
            print_status "⏳ Attente de l'arrêt gracieux... (${count}s)" "$YELLOW"
        fi
    done
    
    # Si le processus est toujours actif, forcer l'arrêt
    if kill -0 "$pid" 2>/dev/null; then
        print_status "⚡ Arrêt forcé du worker $worker_name..." "$RED"
        kill -KILL "$pid" 2>/dev/null
        sleep 2
    fi
    
    # Vérifier que le processus est bien arrêté
    if kill -0 "$pid" 2>/dev/null; then
        print_status "❌ Impossible d'arrêter le worker $worker_name" "$RED"
        return 1
    else
        print_status "✅ Worker $worker_name arrêté avec succès" "$GREEN"
        rm "$pid_file"
        return 0
    fi
}

# Fonction pour arrêter tous les workers
stop_all_workers() {
    print_status "🛑 Arrêt de tous les workers..." "$BLUE"
    
    local stopped_count=0
    local failed_count=0
    
    # Parcourir tous les fichiers PID
    for pid_file in "$PID_DIR"/*.pid; do
        if [ -f "$pid_file" ]; then
            worker_name=$(basename "$pid_file" .pid)
            if stop_worker "$worker_name"; then
                stopped_count=$((stopped_count + 1))
            else
                failed_count=$((failed_count + 1))
            fi
        fi
    done
    
    if [ $failed_count -eq 0 ]; then
        print_status "✅ Tous les workers ont été arrêtés ($stopped_count workers)" "$GREEN"
    else
        print_status "⚠️  $stopped_count workers arrêtés, $failed_count échecs" "$YELLOW"
    fi
    
    # Nettoyer les éventuels processus orphelins
    print_status "🧹 Nettoyage des processus orphelins..." "$BLUE"
    pkill -f "artisan queue:work" 2>/dev/null || true
    
    # Attendre un peu pour s'assurer que tout est nettoyé
    sleep 3
}

# Fonction principale de redémarrage
restart_jobs() {
    log_message "=== Début du redémarrage des jobs ==="
    
    # 1. Arrêter tous les workers existants
    stop_all_workers
    
    # 2. Redémarrer le scheduler Laravel (pour s'assurer qu'il prend en compte les changements)
    print_status "🔄 Redémarrage du scheduler Laravel..." "$BLUE"
    php artisan queue:restart 2>/dev/null || true
    
    # 3. Attendre un peu pour s'assurer que tout est propre
    sleep 2
    
    # 4. Relancer tous les workers
    print_status "🚀 Relancement des workers..." "$GREEN"
    
    if [ -x "$SCRIPT_DIR/start_jobs.sh" ]; then
        "$SCRIPT_DIR/start_jobs.sh"
    else
        print_status "❌ Script start_jobs.sh non trouvé ou non exécutable" "$RED"
        print_status "📝 Veuillez vous assurer que start_jobs.sh existe et est exécutable" "$YELLOW"
        return 1
    fi
    
    log_message "=== Redémarrage des jobs terminé ==="
}

# Vérifier si Laravel est accessible
if ! php artisan --version > /dev/null 2>&1; then
    print_status "❌ Erreur: Laravel n'est pas accessible depuis ce répertoire" "$RED"
    exit 1
fi

# Demander confirmation si des workers sont en cours d'exécution
active_workers=0
for pid_file in "$PID_DIR"/*.pid; do
    if [ -f "$pid_file" ]; then
        pid=$(cat "$pid_file")
        if kill -0 "$pid" 2>/dev/null; then
            active_workers=$((active_workers + 1))
        fi
    fi
done

if [ $active_workers -gt 0 ]; then
    print_status "⚠️  $active_workers worker(s) actif(s) détecté(s)" "$YELLOW"
    
    # Si on est en mode interactif, demander confirmation
    if [ -t 0 ]; then
        echo -n "Voulez-vous vraiment redémarrer tous les workers ? (y/N): "
        read -r response
        case "$response" in
            [yY]|[yY][eE][sS])
                print_status "✅ Confirmation reçue, redémarrage en cours..." "$GREEN"
                ;;
            *)
                print_status "❌ Redémarrage annulé par l'utilisateur" "$YELLOW"
                exit 0
                ;;
        esac
    else
        print_status "🤖 Mode non-interactif détecté, redémarrage automatique..." "$BLUE"
    fi
fi

# Exécuter le redémarrage
restart_jobs

print_status "🎯 Commandes utiles après redémarrage:" "$BLUE"
echo "  - Voir le statut: ./status_jobs.sh"
echo "  - Log unifié: tail -f $UNIFIED_LOG"
echo "  - Logs individuels: tail -f $LOG_DIR/worker_*.log"

print_status "✨ Redémarrage terminé!" "$GREEN"