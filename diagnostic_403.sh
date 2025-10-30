#!/bin/bash

echo "🔍 DIAGNOSTIC 403 FORBIDDEN - AlertContacts Admin"
echo "=================================================="
echo ""

# 1. Vérification des permissions
echo "📁 PERMISSIONS DES FICHIERS ET DOSSIERS:"
echo "----------------------------------------"
echo "public/:"
ls -la public/ | head -10
echo ""
echo "storage/:"
ls -la storage/ | head -5
echo ""
echo "bootstrap/cache/:"
ls -la bootstrap/cache/ | head -5
echo ""

# 2. Vérification de l'existence des fichiers critiques
echo "📄 FICHIERS CRITIQUES:"
echo "----------------------"
files=("public/index.php" "public/.htaccess" "storage/logs" "bootstrap/cache")
for file in "${files[@]}"; do
    if [ -e "$file" ]; then
        echo "✅ $file existe"
        ls -la "$file"
    else
        echo "❌ $file MANQUANT"
    fi
done
echo ""

# 3. Vérification de la configuration Laravel
echo "⚙️  CONFIGURATION LARAVEL:"
echo "--------------------------"
echo "APP_ENV: $(grep APP_ENV .env | cut -d'=' -f2)"
echo "APP_DEBUG: $(grep APP_DEBUG .env | cut -d'=' -f2)"
echo "APP_URL: $(grep APP_URL .env | cut -d'=' -f2)"
echo ""

# 4. Test de connectivité interne
echo "🌐 TEST DE CONNECTIVITÉ:"
echo "------------------------"
if command -v curl &> /dev/null; then
    echo "Test de l'index.php directement:"
    curl -I "http://localhost/index.php" 2>/dev/null || echo "❌ Impossible de tester localhost"
else
    echo "⚠️  curl non disponible pour les tests"
fi
echo ""

# 5. Vérification des logs d'erreur
echo "📋 LOGS D'ERREUR RÉCENTS:"
echo "-------------------------"
if [ -f "storage/logs/laravel.log" ]; then
    echo "Dernières erreurs Laravel:"
    tail -20 storage/logs/laravel.log | grep -E "(ERROR|CRITICAL|403|Forbidden)" || echo "Aucune erreur 403 trouvée dans les logs Laravel"
else
    echo "❌ Fichier de log Laravel introuvable"
fi
echo ""

# 6. Vérification de la configuration du serveur web
echo "🔧 CONFIGURATION SERVEUR WEB:"
echo "-----------------------------"
if [ -f "public/.htaccess" ]; then
    echo "✅ .htaccess existe"
    echo "Contenu du .htaccess (premières lignes):"
    head -10 public/.htaccess
else
    echo "❌ .htaccess manquant dans public/"
fi
echo ""

# 7. Recommandations
echo "💡 RECOMMANDATIONS:"
echo "-------------------"
echo "1. Vérifiez que votre serveur web pointe vers le dossier 'public/'"
echo "2. Assurez-vous que mod_rewrite est activé sur votre serveur"
echo "3. Vérifiez que l'APP_URL dans .env correspond à votre domaine de production"
echo "4. Consultez les logs du serveur web (Apache/Nginx) pour plus de détails"
echo ""

echo "✅ Diagnostic terminé!"
echo "Si le problème persiste, partagez ce rapport avec votre hébergeur."