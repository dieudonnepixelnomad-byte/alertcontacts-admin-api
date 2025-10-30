#!/bin/bash

# Script de diagnostic spécifique aux problèmes Hostinger
echo "🔍 Diagnostic spécifique Hostinger pour AlertContacts Admin..."

echo ""
echo "🌍 1. Vérification de l'environnement..."
echo "APP_ENV: $(php artisan env | grep APP_ENV || echo 'Non défini')"
echo "APP_DEBUG: $(php artisan env | grep APP_DEBUG || echo 'Non défini')"
echo "APP_URL: $(php artisan env | grep APP_URL || echo 'Non défini')"

echo ""
echo "📁 2. Vérification des permissions de fichiers..."
echo "Permissions du dossier storage:"
ls -la storage/
echo ""
echo "Permissions du dossier bootstrap/cache:"
ls -la bootstrap/cache/
echo ""
echo "Permissions du fichier .env:"
ls -la .env 2>/dev/null || echo ".env non trouvé"

echo ""
echo "🔧 3. Vérification de la configuration PHP..."
php -v
echo ""
echo "Extensions PHP chargées (importantes):"
php -m | grep -E "(pdo|mysql|mbstring|openssl|tokenizer|xml|ctype|json|bcmath|fileinfo)"

echo ""
echo "📊 4. Test de connexion à la base de données..."
php artisan tinker --execute="
try {
    \$pdo = DB::connection()->getPdo();
    echo 'Connexion DB: OK' . PHP_EOL;
    echo 'Driver: ' . \$pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . PHP_EOL;
} catch (Exception \$e) {
    echo 'Erreur DB: ' . \$e->getMessage() . PHP_EOL;
}
"

echo ""
echo "👥 5. Test spécifique de l'accès admin..."
php artisan tinker --execute="
use App\Models\User;

echo '=== TEST ACCÈS ADMIN DÉTAILLÉ ===';
\$users = User::all();
foreach (\$users as \$user) {
    echo 'User ID: ' . \$user->id . ' | Email: ' . \$user->email . PHP_EOL;
    echo '  - email_verified_at: ' . (\$user->email_verified_at ? \$user->email_verified_at : 'NULL') . PHP_EOL;
    echo '  - is_admin (attribut): ' . (isset(\$user->attributes['is_admin']) ? (\$user->attributes['is_admin'] ? 'true' : 'false') : 'non défini') . PHP_EOL;
    echo '  - is_admin (propriété): ' . (isset(\$user->is_admin) ? (\$user->is_admin ? 'true' : 'false') : 'non défini') . PHP_EOL;
    
    // Test direct de la base
    \$freshUser = User::where('id', \$user->id)->first();
    echo '  - is_admin (DB fresh): ' . (isset(\$freshUser->is_admin) ? (\$freshUser->is_admin ? 'true' : 'false') : 'non défini') . PHP_EOL;
    
    try {
        \$canAccess = \$user->canAccessPanel(app('filament')->getPanel('admin'));
        echo '  - Accès panel: ' . (\$canAccess ? 'AUTORISÉ' : 'REFUSÉ') . PHP_EOL;
    } catch (Exception \$e) {
        echo '  - Erreur panel: ' . \$e->getMessage() . PHP_EOL;
    }
    echo PHP_EOL;
}
"

echo ""
echo "🗂️ 6. Vérification de la structure de la table users..."
php artisan tinker --execute="
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo '=== STRUCTURE TABLE USERS ===';
try {
    \$columns = DB::select('SHOW COLUMNS FROM users');
    foreach (\$columns as \$column) {
        echo \$column->Field . ' | ' . \$column->Type . ' | ' . \$column->Null . ' | ' . \$column->Default . ' | ' . \$column->Extra . PHP_EOL;
    }
} catch (Exception \$e) {
    echo 'Erreur: ' . \$e->getMessage() . PHP_EOL;
}
"

echo ""
echo "🔄 7. Test de cache et sessions..."
echo "Nettoyage du cache..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

echo ""
echo "📝 8. Vérification des logs récents..."
echo "Dernières erreurs Laravel:"
tail -20 storage/logs/laravel.log 2>/dev/null || echo "Pas de logs Laravel trouvés"

echo ""
echo "🔧 9. Commandes de réparation Hostinger..."
echo "Correction des permissions (si nécessaire):"
echo "chmod -R 755 storage bootstrap/cache"
echo "chown -R \$USER:www-data storage bootstrap/cache"

echo ""
echo "✅ Diagnostic Hostinger terminé !"
echo ""
echo "🚨 Actions recommandées pour Hostinger:"
echo "   1. Vérifier que le champ is_admin existe dans la table users"
echo "   2. S'assurer que APP_ENV=production dans .env"
echo "   3. Corriger les permissions si nécessaire"
echo "   4. Vider tous les caches"
echo "   5. Créer un utilisateur admin avec is_admin=1"