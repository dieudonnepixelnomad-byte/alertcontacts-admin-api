#!/bin/bash

# Script de diagnostic pour vérifier l'état de la production
echo "🔍 Diagnostic de l'état de la production AlertContacts Admin..."

echo ""
echo "📊 1. Vérification de la structure de la table users..."
php artisan tinker --execute="
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo '=== COLONNES DE LA TABLE USERS ===';
\$columns = DB::select('DESCRIBE users');
foreach (\$columns as \$column) {
    echo \$column->Field . ' | ' . \$column->Type . ' | ' . \$column->Null . ' | ' . \$column->Default . PHP_EOL;
}

echo PHP_EOL . '=== VÉRIFICATION COLONNE is_admin ===';
\$hasIsAdmin = Schema::hasColumn('users', 'is_admin');
echo 'Colonne is_admin existe: ' . (\$hasIsAdmin ? 'OUI' : 'NON') . PHP_EOL;
"

echo ""
echo "👥 2. Vérification des utilisateurs existants..."
php artisan tinker --execute="
use App\Models\User;

echo '=== UTILISATEURS DANS LA BASE ===';
\$users = User::all(['id', 'name', 'email', 'email_verified_at']);
foreach (\$users as \$user) {
    echo 'ID: ' . \$user->id . ' | Email: ' . \$user->email . ' | Nom: ' . \$user->name . ' | Vérifié: ' . (\$user->email_verified_at ? 'OUI' : 'NON') . PHP_EOL;
}

echo PHP_EOL . '=== UTILISATEURS AVEC ACCÈS ADMIN ACTUEL ===';
\$adminUsers = User::whereIn('email', ['dieudonnegwet86@gmail.com', 'edwige.gnaly1@gmail.com'])->get(['id', 'name', 'email']);
foreach (\$adminUsers as \$user) {
    echo 'Admin autorisé: ' . \$user->email . ' | Nom: ' . \$user->name . PHP_EOL;
}
"

echo ""
echo "🔧 3. Test de la méthode canAccessPanel actuelle..."
php artisan tinker --execute="
use App\Models\User;
use Filament\Panel;

echo '=== TEST ACCÈS PANEL POUR CHAQUE UTILISATEUR ===';
\$users = User::all();
foreach (\$users as \$user) {
    try {
        \$canAccess = \$user->canAccessPanel(app('filament')->getPanel('admin'));
        echo 'User: ' . \$user->email . ' | Accès: ' . (\$canAccess ? 'AUTORISÉ' : 'REFUSÉ') . PHP_EOL;
    } catch (Exception \$e) {
        echo 'User: ' . \$user->email . ' | Erreur: ' . \$e->getMessage() . PHP_EOL;
    }
}
"

echo ""
echo "🌍 4. Informations sur l'environnement..."
echo "APP_ENV: $(php artisan env | grep APP_ENV)"
echo "APP_DEBUG: $(php artisan env | grep APP_DEBUG)"
echo "APP_URL: $(php artisan env | grep APP_URL)"

echo ""
echo "📋 5. État des migrations..."
php artisan migrate:status | head -20

echo ""
echo "✅ Diagnostic terminé !"
echo ""
echo "📝 Actions recommandées selon les résultats :"
echo "   - Si 'is_admin' n'existe pas : exécuter les migrations"
echo "   - Si aucun utilisateur n'a accès : créer/modifier un utilisateur admin"
echo "   - Si la méthode canAccessPanel refuse l'accès : déployer les modifications du modèle User"