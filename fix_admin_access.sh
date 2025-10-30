#!/bin/bash

# Script pour résoudre le problème d'accès à l'admin Filament
# Usage: ./fix_admin_access.sh

echo "🔧 Résolution du problème d'accès à l'admin Filament..."

# 1. Migrer la base de données pour ajouter le champ is_admin
echo "📊 Migration de la base de données..."
php artisan migrate --force

# 2. Vérifier si un utilisateur admin existe déjà
echo "👤 Vérification des utilisateurs admin existants..."
ADMIN_COUNT=$(php artisan tinker --execute="echo App\Models\User::where('is_admin', true)->count();")

if [ "$ADMIN_COUNT" -eq 0 ]; then
    echo "❌ Aucun utilisateur admin trouvé."
    echo "📝 Création d'un nouvel utilisateur admin..."
    
    # Demander les informations pour créer un admin
    read -p "Email de l'admin: " ADMIN_EMAIL
    read -p "Nom de l'admin: " ADMIN_NAME
    read -s -p "Mot de passe de l'admin: " ADMIN_PASSWORD
    echo ""
    
    # Créer l'utilisateur admin
    php artisan make:admin-user --email="$ADMIN_EMAIL" --name="$ADMIN_NAME" --password="$ADMIN_PASSWORD"
else
    echo "✅ $ADMIN_COUNT utilisateur(s) admin trouvé(s)."
fi

# 3. Mettre à jour un utilisateur existant pour le rendre admin (optionnel)
echo ""
echo "🔄 Voulez-vous mettre à jour un utilisateur existant pour le rendre admin ? (y/N)"
read -r UPDATE_EXISTING

if [[ $UPDATE_EXISTING =~ ^[Yy]$ ]]; then
    read -p "Email de l'utilisateur à promouvoir admin: " USER_EMAIL
    php artisan tinker --execute="
        \$user = App\Models\User::where('email', '$USER_EMAIL')->first();
        if (\$user) {
            \$user->update(['is_admin' => true]);
            echo 'Utilisateur ' . \$user->email . ' promu admin avec succès.';
        } else {
            echo 'Utilisateur non trouvé.';
        }
    "
fi

# 4. Vérifier la configuration
echo ""
echo "🔍 Vérification de la configuration..."
echo "URL de l'admin: $(php artisan route:list | grep adminxyzqwe12345 | head -1 | awk '{print $4}')"
echo "Environnement: $(php artisan env | grep APP_ENV)"

# 5. Nettoyer le cache
echo "🧹 Nettoyage du cache..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo ""
echo "✅ Script terminé !"
echo "🌐 Accédez à l'admin via: https://votre-domaine.com/adminxyzqwe12345"
echo ""
echo "📋 Résumé des modifications:"
echo "   - Champ 'is_admin' ajouté à la table users"
echo "   - Méthode canAccessPanel() mise à jour"
echo "   - Commande make:admin-user disponible"
echo "   - Cache nettoyé"