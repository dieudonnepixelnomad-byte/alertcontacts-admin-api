#!/bin/bash

# Script de vérification de santé des Jobs et Schedulers - AlertContact
# Usage: ./check_jobs_health.sh

echo "🔍 === Vérification de santé des Jobs et Schedulers AlertContact ==="
echo "📅 $(date)"
echo ""

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Fonction pour afficher les sections
print_section() {
    echo -e "${BLUE}$1${NC}"
    echo "----------------------------------------"
}

# Fonction pour vérifier si une commande a réussi
check_status() {
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ OK${NC}"
    else
        echo -e "${RED}❌ ERREUR${NC}"
    fi
}

print_section "1. 📊 État des queues"
php artisan queue:monitor 2>/dev/null
check_status

echo ""
print_section "2. ❌ Jobs échoués"
failed_jobs=$(php artisan queue:failed --format=json 2>/dev/null | jq length 2>/dev/null || echo "0")
if [ "$failed_jobs" -eq 0 ]; then
    echo -e "${GREEN}✅ Aucun job échoué${NC}"
else
    echo -e "${RED}❌ $failed_jobs job(s) échoué(s)${NC}"
    php artisan queue:failed
fi

echo ""
print_section "3. ⏰ Tâches planifiées"
php artisan schedule:list 2>/dev/null
check_status

echo ""
print_section "4. 🧪 Test du scheduler"
php artisan schedule:run --verbose 2>/dev/null
check_status

echo ""
print_section "5. ❄️ Statistiques des cooldowns"
php artisan cooldown:manage stats 2>/dev/null
check_status

echo ""
print_section "6. 🔥 Vérification Firebase (logs récents)"
recent_firebase_logs=$(tail -n 100 storage/logs/laravel.log | grep -i firebase | wc -l)
if [ "$recent_firebase_logs" -gt 0 ]; then
    echo -e "${GREEN}✅ $recent_firebase_logs entrées Firebase trouvées dans les logs récents${NC}"
else
    echo -e "${YELLOW}⚠️ Aucune activité Firebase récente détectée${NC}"
fi

echo ""
print_section "7. 📍 Vérification géoprocessing (logs récents)"
recent_geo_logs=$(tail -n 100 storage/logs/laravel.log | grep -i "location batch\|geoprocessing" | wc -l)
if [ "$recent_geo_logs" -gt 0 ]; then
    echo -e "${GREEN}✅ $recent_geo_logs entrées de géoprocessing trouvées${NC}"
else
    echo -e "${YELLOW}⚠️ Aucune activité de géoprocessing récente${NC}"
fi

echo ""
print_section "8. 🛡️ Vérification des rappels de zone de sécurité"
recent_safezone_logs=$(tail -n 100 storage/logs/laravel.log | grep -i "safe zone.*reminder" | wc -l)
if [ "$recent_safezone_logs" -gt 0 ]; then
    echo -e "${GREEN}✅ $recent_safezone_logs rappels de zone de sécurité trouvés${NC}"
else
    echo -e "${YELLOW}⚠️ Aucun rappel de zone de sécurité récent${NC}"
fi

echo ""
print_section "9. 💾 Espace disque des logs"
log_size=$(du -sh storage/logs/ 2>/dev/null | cut -f1)
echo "📁 Taille des logs: $log_size"

echo ""
print_section "10. 🔄 Processus queue workers actifs"
queue_processes=$(ps aux | grep "queue:work" | grep -v grep | wc -l)
if [ "$queue_processes" -gt 0 ]; then
    echo -e "${GREEN}✅ $queue_processes worker(s) de queue actif(s)${NC}"
    ps aux | grep "queue:work" | grep -v grep
else
    echo -e "${RED}❌ Aucun worker de queue actif${NC}"
    echo -e "${YELLOW}💡 Lancez: php artisan queue:work --daemon${NC}"
fi

echo ""
print_section "📋 Résumé"
echo "🕐 Vérification terminée à $(date)"
echo ""
echo -e "${BLUE}💡 Commandes utiles:${NC}"
echo "   • Surveiller les queues: php artisan queue:work --verbose"
echo "   • Voir les logs en temps réel: tail -f storage/logs/laravel.log"
echo "   • Interface Telescope: /telescope"
echo "   • Redémarrer les workers: php artisan queue:restart"
echo ""
echo -e "${GREEN}✨ Pour plus d'informations, consultez: docs/GUIDE_MONITORING_JOBS_SCHEDULERS.md${NC}"