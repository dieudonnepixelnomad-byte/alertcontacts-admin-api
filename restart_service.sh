#!/bin/bash

# === SCRIPT DE REDÉMARRAGE DES SERVICES ===

PROJECT_PATH="/home/u918130518/domains/alertcontacts.net/public_html/mobile"
PID_PATH="$PROJECT_PATH/storage/pids"

echo "🔄 Redémarrage des services AlertContact..."

# Arrêter tous les services
echo "🛑 Arrêt des services existants..."
if [ -d "$PID_PATH" ]; then
    for pid_file in "$PID_PATH"/*.pid; do
        if [ -f "$pid_file" ]; then
            pid=$(cat "$pid_file")
            if kill -0 "$pid" 2>/dev/null; then
                kill "$pid"
                echo "   - Arrêt du processus $pid"
            fi
            rm -f "$pid_file"
        fi
    done
fi

# Attendre que les processus se terminent
sleep 3

# Redémarrer tous les services
echo "🚀 Redémarrage..."
./start_all_services.sh