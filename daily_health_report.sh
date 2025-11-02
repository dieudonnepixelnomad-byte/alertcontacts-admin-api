#!/bin/bash

# === RAPPORT DE SANTÉ QUOTIDIEN ===

PROJECT_PATH="/home/u918130518/domains/alertcontacts.net/public_html/mobile"
REPORT_PATH="$PROJECT_PATH/storage/logs/reports"

mkdir -p "$REPORT_PATH"

REPORT_FILE="$REPORT_PATH/health_report_$(date +%Y%m%d).log"

{
    echo "=== RAPPORT DE SANTÉ ALERTCONTACT - $(date) ==="
    echo ""

    echo "🔍 STATUT DES SERVICES :"
    ./check_services_status.sh
    echo ""

    echo "📊 STATISTIQUES DES QUEUES :"
    cd "$PROJECT_PATH"
    php artisan queue:monitor default,high,low,notifications,cleanup --once
    echo ""

    echo "🧹 STATISTIQUES DE NETTOYAGE :"
    php artisan cleanup:old-data --stats
    echo ""

    echo "💾 ESPACE DISQUE :"
    df -h "$PROJECT_PATH"
    echo ""

    echo "📁 TAILLE DES LOGS :"
    du -sh "$PROJECT_PATH/storage/logs"/*
    echo ""

    echo "⚠️ JOBS ÉCHOUÉS (dernières 24h) :"
    php artisan queue:failed | head -20
    echo ""

    echo "🔄 DERNIÈRES EXÉCUTIONS DU SCHEDULER :"
    tail -20 "$PROJECT_PATH/storage/logs/scheduler/scheduler.log"

} > "$REPORT_FILE"

# Envoyer le rapport par email si configuré
if [ -n "$MONITORING_EMAIL" ]; then
    mail -s "AlertContact - Rapport de Santé Quotidien" "$MONITORING_EMAIL" < "$REPORT_FILE"
fi

echo "📋 Rapport de santé généré : $REPORT_FILE"
