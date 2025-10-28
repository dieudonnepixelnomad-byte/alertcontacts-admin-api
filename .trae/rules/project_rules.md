Tu es un expert ingenieur senior en developpement de backend et api avec Laravel 12 et Filament pour l'admin web et tu vas m'aider a realiser ce projet

📘 Cahier des Charges — Backend Laravel (Alerte Contact)
1. Présentation générale

Nom du projet : AlertContact

Objectif : Application mobile de sécurité personnelle permettant de protéger et rassurer ses proches (enfants, seniors, femmes seules, personnes vulnérables).

Plateformes : Android & iOS (Flutter).

Backend : Laravel 12 + PostgreSQL (PostGIS), API REST/JSON + notifications temps réel (Firebase).

Business model : Freemium + Premium (zones illimitées, historiques, fonctionnalités avancées).

2. Cibles & besoins

Parents : s’assurer que les enfants sont en sécurité (maison, école, trajets).

Aidants & familles : surveiller les déplacements de personnes âgées ou à mobilité réduite.

Jeunes & femmes seules : être alertés lorsqu’ils approchent d’une zone signalée comme dangereuse.

Voyageurs : connaître les zones à risque dans une ville étrangère.

3. Fonctionnalités principales
3.1 Zones de danger

Affichage sur la carte (Google Maps).

Création de zones de danger : nom, type (agression, vol, accident…), gravité, description, coordonnées.

Détection des doublons & fusion automatique si zones proches.

Notifications (vocale, vibration, push) lors de l’approche.

Durée de vie limitée (30 jours).

Confirmation d’un danger ou signalement d’abus.

3.2 Zones de sécurité

Création de zones privées (cercle ou polygone).

Affectation d’un ou plusieurs proches à une zone.

Notifications entrée/sortie d’un proche.

Anti-faux positifs (hystérésis GPS, délais).

Paramétrage horaires actifs.

Liste des zones sécurisées avec état et historique.

3.3 Gestion des proches

Invitation par lien magique ou QR Code.

Acceptation avec consentement explicite.

Choix du niveau de partage (temps réel, alertes uniquement, aucun partage).

Granularité : activer/désactiver partage par proche.

Transparence : journal d’accès + notifications de modification.

3.4 Notifications & alertes

Multi-niveaux : critique (vibration + voix) / info (push simple).

Cooldown (pas de spam, min. 15 min).

Mode discret (vibration seule).

Heures calmes configurables.

3.5 Carte interactive

Google Maps avec :

Zones de danger (rouges/oranges avec badge).

Zones sécurisées (vert translucide).

Clustering si forte densité.

Filtres : gravité, fraîcheur, type, distance.

Bottom sheet détail (type, gravité, nb confirmations, date).

“Peek résumé” (3 dangers proches – 2 zones actives).

3.6 Authentification & comptes

Splash → Onboarding → Authentification.

Login : Email/Mot de passe + Google + Apple (iOS).

Inscription simple (Nom, Email, Mot de passe).

Mot de passe oublié.

3.7 Permissions & setup initial

Permission localisation (explication + bouton).

Permission notifications.

Setup rapide : 1ère zone de sécurité + ajout d’un proche.

3.8 Paramètres & confidentialité

Paramètres globaux : couper le partage de localisation.

Granularité par proche.

Historique des accès.

RGPD : export données, suppression compte.

4. Spécifications backend Laravel
4.1 Architecture

Framework : Laravel 12 (PHP 8.3).

Base de données : PostgreSQL + PostGIS.

API : REST JSON.

Auth : Laravel Sanctum.

Services :

Jobs/queues via Horizon.

Notifications via FCM.

Cron (expiration zones, invitations).

4.2 Modèles & tables principales

users : infos compte, préférences, auth.

relationships : gestion des proches, consentement, niveaux de partage.

invitations : liens magiques, tokens QR.

danger_zones : zones publiques, gravité, coordonnées.

danger_reports : confirmations, agrégations.

safe_zones : zones privées (cercle/polygone).

safezone_assignments : affectations proches/zones.

events : entrées/sorties zones, alertes danger.

4.3 Endpoints API

Auth : register, login, logout, social, me.

Proches : invite, accept, liste, update partage, delete.

Zones de danger : create, list (par zone/coordonnées), confirm, abuse.

Zones de sécurité : create, list, update, delete, assign proche.

Événements : create (entrée/sortie), list.

Notifications : test, envoi automatique (jobs).

4.4 Règles métier

Fusion zones danger si distance ≤ 100 m ou recouvrement ≥ 30 %.

Expiration danger après 30 jours.

Granularité partage par proche respectée par API.

Hystérésis GPS pour éviter faux positifs.

4.5 Sécurité & RGPD

Auth sécurisée via Sanctum.

Chiffrement données sensibles.

Export/suppression données utilisateur.

Journalisation des accès.

4.6 Monitoring & admin

Back-office Nova/Filament pour modération.

Logs via Laravel Telescope.

Metrics New Relic / Sentry.

5. Livrables & planning

Semaine 1–2 : setup Laravel, DB, Auth.

Semaine 3–4 : gestion Proches & Invitations.

Semaine 5–6 : Zones Danger & Sécurité.

Semaine 7–8 : Notifications push + Jobs.

Semaine 9 : QA, tests, staging.

Semaine 10 : mise en production.

Si tu veux faire un test en demarrant le serveur, tu peux utiliser la commande suivante :
```
php artisan serve--host=127.0.0.1 --port=8000
```
