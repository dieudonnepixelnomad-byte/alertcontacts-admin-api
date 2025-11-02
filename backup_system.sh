#!/bin/bash

# === SYSTÈME DE BACKUP AUTOMATIQUE ALERTCONTACT - HOSTINGER ===

set -e

# Configuration Hostinger
PROJECT_PATH="/home/u918130518/domains/alertcontacts.net/public_html/mobile"
BACKUP_PATH="/home/u918130518/backups/alertcontact"
DB_HOST="localhost"
DB_DATABASE="u918130518_alertcontacts"
DB_USERNAME="u918130518_alertcontacts"
DB_PASSWORD="${DB_BACKUP_PASSWORD:-}"

# Configuration
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
RETENTION_DAYS=15  # Optimisé pour Hostinger
MAX_DISK_USAGE=80  # Pourcentage maximum d'utilisation disque

# Couleurs
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Fonction de logging
log() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

error() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1"
}

warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1"
}

# Créer le répertoire de backup
mkdir -p "$BACKUP_PATH"

log "🛡️ Démarrage du backup AlertContact..."

# === 1. VÉRIFICATION ESPACE DISQUE ===
log "💾 Vérification de l'espace disque..."

DISK_USAGE=$(df /home/u918130518 | awk 'NR==2 {print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -gt "$MAX_DISK_USAGE" ]; then
    error "Espace disque critique: ${DISK_USAGE}% utilisé"
    exit 1
fi

log "✅ Espace disque OK: ${DISK_USAGE}% utilisé"

# === 2. BACKUP BASE DE DONNÉES ===
log "🗄️ Backup de la base de données..."

if [ -z "$DB_PASSWORD" ]; then
    error "Mot de passe de base de données non configuré (DB_BACKUP_PASSWORD)"
    exit 1
fi

mysqldump -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
    --single-transaction \
    --routines \
    --triggers \
    > "$BACKUP_PATH/database_${TIMESTAMP}.sql"

log "✅ Backup base de données terminé"

# === 3. BACKUP CONFIGURATION ===
log "⚙️ Backup de la configuration..."

cd "$PROJECT_PATH"
tar -czf "$BACKUP_PATH/config_${TIMESTAMP}.tar.gz" \
    .env \
    config/ \
    routes/ \
    composer.json \
    composer.lock \
    artisan

log "✅ Backup configuration terminé"

# === 4. BACKUP FICHIERS CRITIQUES ===
log "📁 Backup des fichiers critiques de l'application..."

tar -czf "$BACKUP_PATH/app_critical_${TIMESTAMP}.tar.gz" \
    app/ \
    database/migrations/ \
    database/seeders/ \
    resources/views/ \
    --exclude='app/storage' \
    --exclude='*.log'

log "✅ Backup fichiers critiques terminé"

# === 5. BACKUP PIDs ET LOGS RÉCENTS ===
log "📊 Backup des PIDs et logs récents..."

# PIDs des services
if [ -d "storage/pids" ]; then
    cp -r storage/pids "$BACKUP_PATH/pids_${TIMESTAMP}/"
fi

# Logs des 7 derniers jours
mkdir -p "$BACKUP_PATH/logs_${TIMESTAMP}"
find storage/logs -name "*.log" -mtime -7 -exec cp {} "$BACKUP_PATH/logs_${TIMESTAMP}/" \;

log "✅ Backup PIDs et logs terminé"

# === 6. MÉTADONNÉES DU BACKUP ===
log "📋 Création des métadonnées..."

cat > "$BACKUP_PATH/backup_info_${TIMESTAMP}.txt" << EOF
=== BACKUP ALERTCONTACT ===
Date: $(date)
Timestamp: $TIMESTAMP
Serveur: Hostinger
Projet: $PROJECT_PATH
Base de données: $DB_DATABASE

=== CONTENU ===
- database_${TIMESTAMP}.sql ($(du -h "$BACKUP_PATH/database_${TIMESTAMP}.sql" | cut -f1))
- config_${TIMESTAMP}.tar.gz ($(du -h "$BACKUP_PATH/config_${TIMESTAMP}.tar.gz" | cut -f1))
- app_critical_${TIMESTAMP}.tar.gz ($(du -h "$BACKUP_PATH/app_critical_${TIMESTAMP}.tar.gz" | cut -f1))
- pids_${TIMESTAMP}/ ($(du -sh "$BACKUP_PATH/pids_${TIMESTAMP}" 2>/dev/null | cut -f1 || echo "0"))
- logs_${TIMESTAMP}/ ($(du -sh "$BACKUP_PATH/logs_${TIMESTAMP}" | cut -f1))

=== STATISTIQUES ===
Espace disque avant backup: ${DISK_USAGE}%
Taille totale backup: $(du -sh "$BACKUP_PATH" | cut -f1)

=== SERVICES ACTIFS ===
$(ps aux | grep -E "queue:work|schedule:work" | grep -v grep || echo "Aucun service détecté")
EOF

log "✅ Métadonnées créées"

# === 7. NETTOYAGE DES ANCIENS BACKUPS ===
log "🧹 Nettoyage des anciens backups (> $RETENTION_DAYS jours)..."

find "$BACKUP_PATH" -name "*_[0-9]*" -mtime +$RETENTION_DAYS -delete 2>/dev/null || true

BACKUP_COUNT=$(find "$BACKUP_PATH" -name "backup_info_*.txt" | wc -l)
log "✅ Nettoyage terminé. $BACKUP_COUNT backups conservés"

# === 8. VÉRIFICATION FINALE ===
log "🔍 Vérification finale..."

TOTAL_BACKUP_SIZE=$(du -sh "$BACKUP_PATH" | cut -f1)
FINAL_DISK_USAGE=$(df /home/u918130518 | awk 'NR==2 {print $5}' | sed 's/%//')

log "📊 Résumé du backup :"
log "   - Taille totale: $TOTAL_BACKUP_SIZE"
log "   - Espace disque après backup: ${FINAL_DISK_USAGE}%"
log "   - Backups conservés: $BACKUP_COUNT"

if [ "$FINAL_DISK_USAGE" -gt 90 ]; then
    warning "Espace disque critique après backup: ${FINAL_DISK_USAGE}%"
fi

log "🎉 Backup terminé avec succès !"

# === 9. NOTIFICATION (optionnelle) ===
if [ -n "$MONITORING_EMAIL" ]; then
    echo "Backup AlertContact terminé avec succès
    
Timestamp: $TIMESTAMP
Taille: $TOTAL_BACKUP_SIZE
Espace disque: ${FINAL_DISK_USAGE}%
Backups conservés: $BACKUP_COUNT" | mail -s "AlertContact - Backup Success" "$MONITORING_EMAIL"
fi