#!/bin/bash

# === SCRIPT D'ARRÊT DES SERVICES ===

PROJECT_PATH="/home/u918130518/domains/alertcontacts.net/public_html/mobile"
PID_PATH="$PROJECT_PATH/storage/pids"

echo "🛑 Arrêt de tous les services AlertContact..."

# Arrêter proprement tous les services
if [ -d "$PID_PATH" ]; then
    for pid_file in "$PID_PATH"/*.pid; do
        if [ -f "$pid_file" ]; then
            service_name=$(basename "$pid_file" .pid)
            pid=$(cat "$pid_file")
            
            if kill -0 "$pid" 2>/dev/null; then
                echo "   - Arrêt de $service_name (PID: $pid)"
                kill -TERM "$pid"
                
                # Attendre l'arrêt gracieux
                for i in {1..10}; do
                    if ! kill -0 "$pid" 2>/dev/null; then
                        break
                    fi
                    sleep 1
                done
                
                # Forcer l'arrêt si nécessaire
                if kill -0 "$pid" 2>/dev/null; then
                    echo "   - Arrêt forcé de $service_name"
                    kill -KILL "$pid"
                fi
            fi
            
            rm -f "$pid_file"
        fi
    done
fi

# Nettoyer les processus orphelins
pkill -f "artisan queue:work" 2>/dev/null || true
pkill -f "artisan schedule:work" 2>/dev/null || true
pkill -f "artisan horizon" 2>/dev/null || true

echo "✅ Tous les services ont été arrêtés"