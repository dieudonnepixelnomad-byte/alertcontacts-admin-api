#!/bin/bash

# === SCRIPT DE VÉRIFICATION DES SERVICES ===

PROJECT_PATH="/home/u918130518/domains/alertcontacts.net/public_html/mobile"
PID_PATH="$PROJECT_PATH/storage/pids"

# Couleurs
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}🔍 Vérification des services AlertContact${NC}"
echo "=================================================="

# Fonction pour vérifier un service
check_service() {
    local service_name=$1
    local pid_file=$2

    if [ -f "$pid_file" ]; then
        local pid=$(cat "$pid_file")
        if kill -0 "$pid" 2>/dev/null; then
            echo -e "✅ $service_name: ${GREEN}ACTIF${NC} (PID: $pid)"
            return 0
        else
            echo -e "❌ $service_name: ${RED}ARRÊTÉ${NC} (PID obsolète: $pid)"
            return 1
        fi
    else
        echo -e "❌ $service_name: ${RED}NON DÉMARRÉ${NC} (pas de fichier PID)"
        return 1
    fi
}

# Vérifier tous les services
SERVICES_OK=0

check_service "Scheduler" "$PID_PATH/scheduler.pid" && ((SERVICES_OK++))
check_service "Worker Principal" "$PID_PATH/worker-default.pid" && ((SERVICES_OK++))
check_service "Worker Notifications" "$PID_PATH/worker-notifications.pid" && ((SERVICES_OK++))
check_service "Worker Nettoyage" "$PID_PATH/worker-cleanup.pid" && ((SERVICES_OK++))
check_service "Monitoring" "$PID_PATH/monitor.pid" && ((SERVICES_OK++))

echo "=================================================="

if [ $SERVICES_OK -eq 5 ]; then
    echo -e "🎉 ${GREEN}Tous les services sont opérationnels !${NC}"
else
    echo -e "⚠️ ${YELLOW}$SERVICES_OK/5 services actifs${NC}"
    echo -e "💡 Pour redémarrer : ${YELLOW}./restart_services.sh${NC}"
fi

# Afficher les statistiques des queues
echo ""
echo -e "${GREEN}📊 Statistiques des queues :${NC}"
cd "$PROJECT_PATH"
php artisan queue:monitor default,high,low,notifications,cleanup --once 2>/dev/null || echo "Impossible de récupérer les stats"
