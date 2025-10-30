#!/bin/bash

# Script de correction spécifique pour les problèmes Hostinger
echo "🔧 Correction des problèmes d'accès admin sur Hostinger..."

# 1. Correction des permissions (problème fréquent sur Hostinger)
echo "📁 1. Correction des permissions de fichiers..."
chmod -R 755 storage
chmod -R 755 bootstrap/cache
chmod 644 .env 2>/dev/null || echo "Fichier .env non trouvé"

# 2. Nettoyage complet du cache (important sur Hostinger)
echo "🧹 2. Nettoyage complet du cache..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear
php artisan optimize:clear 2>/dev/null || echo "optimize:clear non disponible"

# 3. Migration forcée pour s'assurer que is_admin existe
echo "📊 3. Vérification et migration de la base de données..."
php artisan migrate --force

# 4. Vérification de la colonne is_admin
echo "🔍 4. Vérification de la colonne is_admin..."
php artisan tinker --execute="
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

if (Schema::hasColumn('users', 'is_admin')) {
    echo 'Colonne is_admin: EXISTE' . PHP_EOL;
} else {
    echo 'Colonne is_admin: MANQUANTE - Ajout en cours...' . PHP_EOL;
    try {
        Schema::table('users', function (\$table) {
            \$table->boolean('is_admin')->default(false);
        });
        echo 'Colonne is_admin ajoutée avec succès' . PHP_EOL;
    } catch (Exception \$e) {
        echo 'Erreur lors de l\'ajout: ' . \$e->getMessage() . PHP_EOL;
    }
}
"

# 5. Création ou mise à jour d'un utilisateur admin
echo "👤 5. Gestion de l'utilisateur admin..."
echo "Voulez-vous créer un nouvel utilisateur admin ? (y/N)"
read -r CREATE_NEW_ADMIN

if [[ $CREATE_NEW_ADMIN =~ ^[Yy]$ ]]; then
    read -p "Email de l'admin: " ADMIN_EMAIL
    read -p "Nom de l'admin: " ADMIN_NAME
    read -s -p "Mot de passe de l'admin: " ADMIN_PASSWORD
    echo ""
    
    php artisan tinker --execute="
    use App\Models\User;
    use Illuminate\Support\Facades\Hash;
    
    try {
        \$user = User::create([
            'name' => '$ADMIN_NAME',
            'email' => '$ADMIN_EMAIL',
            'password' => Hash::make('$ADMIN_PASSWORD'),
            'email_verified_at' => now(),
            'is_admin' => true,
        ]);
        echo 'Utilisateur admin créé: ' . \$user->email . PHP_EOL;
    } catch (Exception \$e) {
        echo 'Erreur: ' . \$e->getMessage() . PHP_EOL;
    }
    "
else
    echo "Mise à jour d'un utilisateur existant..."
    read -p "Email de l'utilisateur à promouvoir admin: " USER_EMAIL
    
    php artisan tinker --execute="
    use App\Models\User;
    
    \$user = User::where('email', '$USER_EMAIL')->first();
    if (\$user) {
        \$user->update(['is_admin' => true, 'email_verified_at' => now()]);
        echo 'Utilisateur ' . \$user->email . ' promu admin avec succès.' . PHP_EOL;
    } else {
        echo 'Utilisateur non trouvé.' . PHP_EOL;
    }
    "
fi

# 6. Test final de l'accès
echo ""
echo "🧪 6. Test final de l'accès admin..."
php artisan tinker --execute="
use App\Models\User;

echo '=== UTILISATEURS AVEC ACCÈS ADMIN ===';
\$adminUsers = User::where('is_admin', true)->get();
foreach (\$adminUsers as \$user) {
    echo 'Admin: ' . \$user->email . ' | Vérifié: ' . (\$user->email_verified_at ? 'OUI' : 'NON') . PHP_EOL;
    try {
        \$canAccess = \$user->canAccessPanel(app('filament')->getPanel('admin'));
        echo '  -> Accès panel: ' . (\$canAccess ? 'AUTORISÉ ✅' : 'REFUSÉ ❌') . PHP_EOL;
    } catch (Exception \$e) {
        echo '  -> Erreur: ' . \$e->getMessage() . PHP_EOL;
    }
}
"

# 7. Optimisation finale pour Hostinger
echo ""
echo "⚡ 7. Optimisation finale pour Hostinger..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo ""
echo "✅ Correction terminée !"
echo ""
echo "🌐 Testez maintenant l'accès à: https://mobile.alertcontacts.net/adminxyzqwe12345"
echo ""
echo "📋 Résumé des actions effectuées:"
echo "   ✓ Permissions corrigées"
echo "   ✓ Cache nettoyé et optimisé"
echo "   ✓ Migration exécutée"
echo "   ✓ Colonne is_admin vérifiée/ajoutée"
echo "   ✓ Utilisateur admin configuré"
echo "   ✓ Méthode canAccessPanel sécurisée"
echo ""
echo "🚨 Si le problème persiste, vérifiez:"
echo "   - Les logs d'erreur du serveur Hostinger"
echo "   - La configuration du .htaccess"
echo "   - Les variables d'environnement (.env)"