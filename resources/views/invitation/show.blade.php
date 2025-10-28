<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation AlertContact - Rejoignez votre réseau de sécurité</title>
    <meta name="description" content="Vous avez été invité(e) à rejoindre un réseau de sécurité AlertContact. Ouvrez l'application ou téléchargez-la maintenant.">
    
    <!-- Open Graph pour WhatsApp/réseaux sociaux -->
    <meta property="og:title" content="Invitation AlertContact">
    <meta property="og:description" content="Rejoignez votre réseau de sécurité AlertContact">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ request()->url() }}">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #006970 0%, #004d54 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            text-align: center;
            max-width: 420px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            border-radius: 24px;
            padding: 40px 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 20px;
            margin: 0 auto 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            color: #006970;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            font-size: 28px;
            margin-bottom: 12px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .subtitle {
            opacity: 0.9;
            margin-bottom: 32px;
            font-size: 16px;
            line-height: 1.5;
        }
        
        .btn {
            display: inline-block;
            background: white;
            color: #006970;
            padding: 16px 32px;
            border-radius: 16px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            margin: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            min-width: 200px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }
        
        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.4);
            padding: 14px 30px;
            font-size: 14px;
            min-width: 180px;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.6);
        }
        
        .status {
            margin-top: 24px;
            padding: 20px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.1);
            font-size: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            margin-right: 12px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .download-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .download-title {
            font-size: 18px;
            margin-bottom: 16px;
            opacity: 0.9;
        }
        
        .store-buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
            align-items: center;
        }
        
        @media (min-width: 480px) {
            .store-buttons {
                flex-direction: row;
                justify-content: center;
            }
        }
        
        .error {
            background: rgba(255, 107, 107, 0.2);
            border: 1px solid rgba(255, 107, 107, 0.3);
            color: #ffcccb;
        }
        
        .success {
            background: rgba(72, 187, 120, 0.2);
            border: 1px solid rgba(72, 187, 120, 0.3);
            color: #c6f6d5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">AC</div>
        <h1>Invitation AlertContact</h1>
        <p class="subtitle">Vous avez été invité(e) à rejoindre un réseau de sécurité AlertContact pour protéger et rassurer vos proches.</p>
        
        <div id="status" class="status">
            <div class="loading"></div>
            Tentative d'ouverture de l'application...
        </div>
        
        <div id="actions" style="display: none;">
            <a href="{{ $appUrl }}" id="openApp" class="btn">🚀 Ouvrir AlertContact</a>
            
            <div class="download-section">
                <div class="download-title">Application non installée ?</div>
                <div class="store-buttons">
                    <a href="{{ $playStoreUrl }}" class="btn btn-secondary">
                        📱 Android
                    </a>
                    <a href="{{ $appStoreUrl }}" class="btn btn-secondary">
                        🍎 iOS
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables passées depuis Laravel
        const token = @json($token);
        const appUrl = @json($appUrl);
        
        // Éléments DOM
        const statusEl = document.getElementById('status');
        const actionsEl = document.getElementById('actions');
        const openAppBtn = document.getElementById('openApp');
        
        // Fonction pour afficher un message d'erreur
        function showError(message) {
            statusEl.innerHTML = `❌ ${message}`;
            statusEl.className = 'status error';
            actionsEl.style.display = 'block';
        }
        
        // Fonction pour afficher un message de succès
        function showSuccess(message) {
            statusEl.innerHTML = `✅ ${message}`;
            statusEl.className = 'status success';
        }
        
        // Vérifier si le token est présent
        if (!token || token.trim() === '') {
            showError('Lien d\'invitation invalide ou expiré');
        } else {
            // Log pour debug
            console.log('Token reçu:', token);
            console.log('URL de l\'app:', appUrl);
            
            // Tentative automatique d'ouverture de l'application après 1 seconde
            setTimeout(() => {
                try {
                    // Créer un lien invisible et le cliquer
                    const link = document.createElement('a');
                    link.href = appUrl;
                    link.style.display = 'none';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // Après 3 secondes, vérifier si l'utilisateur est toujours là
                    setTimeout(() => {
                        if (!document.hidden) {
                            // L'utilisateur est toujours sur la page, l'app n'est probablement pas installée
                            statusEl.innerHTML = '📱 Application non détectée';
                            statusEl.className = 'status';
                            actionsEl.style.display = 'block';
                        }
                    }, 3000);
                    
                } catch (error) {
                    console.error('Erreur lors de l\'ouverture de l\'app:', error);
                    showError('Impossible d\'ouvrir l\'application');
                }
            }, 1000);
        }
        
        // Détecter si l'utilisateur revient sur la page (app pas installée ou échec)
        let wasHidden = false;
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                wasHidden = true;
                // L'utilisateur a quitté la page, probablement vers l'app
                showSuccess('Redirection vers l\'application...');
            } else if (wasHidden) {
                // L'utilisateur est revenu, l'app n'est probablement pas installée
                statusEl.innerHTML = '📱 Application non installée ?';
                statusEl.className = 'status';
                actionsEl.style.display = 'block';
            }
        });
        
        // Gestion du clic sur le bouton d'ouverture manuelle
        openAppBtn.addEventListener('click', (e) => {
            e.preventDefault();
            
            try {
                window.location.href = appUrl;
                
                // Feedback visuel
                showSuccess('Tentative d\'ouverture...');
                
                // Si après 2 secondes l'utilisateur est toujours là, afficher les options
                setTimeout(() => {
                    if (!document.hidden) {
                        statusEl.innerHTML = '📱 Application non installée ?';
                        statusEl.className = 'status';
                    }
                }, 2000);
                
            } catch (error) {
                console.error('Erreur:', error);
                showError('Erreur lors de l\'ouverture');
            }
        });
    </script>
</body>
</html>