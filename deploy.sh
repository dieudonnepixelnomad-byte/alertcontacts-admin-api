#!/bin/bash

# Script de déploiement pour AlertContacts Admin sur Hostinger
# Usage: ./deploy.sh

echo "🚀 Début du déploiement AlertContacts Admin..."

# Vérification que nous sommes dans le bon répertoire
if [ ! -f "artisan" ]; then
    echo "❌ Erreur: Ce script doit être exécuté depuis la racine du projet Laravel"
    exit 1
fi

# 1. Optimisation pour la production
echo "📦 Optimisation des fichiers pour la production..."

# Installation des dépendances sans les dev
composer install --optimize-autoloader --no-dev --no-interaction

# Génération de la clé d'application si elle n'existe pas
if grep -q "APP_KEY=$" .env; then
    echo "🔑 Génération de la clé d'application..."
    php artisan key:generate --force
fi

# Cache des configurations
echo "⚡ Mise en cache des configurations..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimisation de l'autoloader
composer dump-autoload --optimize

# 2. Migration de la base de données
echo "🗄️ Migration de la base de données..."
php artisan migrate --force

# 3. Nettoyage des caches
echo "🧹 Nettoyage des anciens caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 4. Création du lien symbolique pour le storage
echo "🔗 Création du lien symbolique pour le storage..."
php artisan storage:link

# 5. Optimisation finale
echo "🎯 Optimisation finale..."
php artisan optimize

# 6. Permissions des dossiers
echo "🔐 Configuration des permissions..."
chmod -R 755 storage
chmod -R 755 bootstrap/cache

echo "✅ Déploiement terminé avec succès!"
echo "📋 N'oubliez pas de:"
echo "   - Configurer votre fichier .env avec les bonnes valeurs"
echo "   - Pointer votre domaine vers le dossier public/"
echo "   - Configurer SSL"
echo "   - Tester toutes les fonctionnalités"