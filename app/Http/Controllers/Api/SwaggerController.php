<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * @OA\Info(
 *     title="AlertContacts API",
 *     version="1.0.0",
 *     description="API de sécurité personnelle pour protéger et rassurer ses proches. Cette API permet de gérer les zones de sécurité, zones de danger, invitations, relations entre utilisateurs et notifications en temps réel.",
 *     @OA\Contact(
 *         email="support@alertcontacts.net",
 *         name="Support AlertContacts"
 *     ),
 *     @OA\License(
 *         name="Propriétaire",
 *         url="https://alertcontacts.net/terms"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="https://mobile.alertcontacts.net/api",
 *     description="Serveur de production"
 * )
 * 
 * @OA\Server(
 *     url="http://localhost:8000/api",
 *     description="Serveur de développement local"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Authentification via Laravel Sanctum. Utilisez le token obtenu lors de la connexion."
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="Endpoints d'authentification (connexion, inscription, déconnexion)"
 * )
 * 
 * @OA\Tag(
 *     name="User Profile",
 *     description="Gestion du profil utilisateur"
 * )
 * 
 * @OA\Tag(
 *     name="Safe Zones",
 *     description="Gestion des zones de sécurité privées"
 * )
 * 
 * @OA\Tag(
 *     name="Danger Zones",
 *     description="Gestion des zones de danger publiques"
 * )
 * 
 * @OA\Tag(
 *     name="Invitations",
 *     description="Système d'invitations entre utilisateurs"
 * )
 * 
 * @OA\Tag(
 *     name="Relationships",
 *     description="Gestion des relations entre proches"
 * )
 * 
 * @OA\Tag(
 *     name="Locations",
 *     description="Ingestion et gestion des positions GPS"
 * )
 * 
 * @OA\Tag(
 *     name="Notifications",
 *     description="Gestion des notifications et alertes"
 * )
 * 
 * @OA\Tag(
 *     name="Activities",
 *     description="Historique des activités utilisateur"
 * )
 * 
 * @OA\Tag(
 *     name="Feedback",
 *     description="Système de feedback et suggestions"
 * )
 */
class SwaggerController extends Controller
{
    // Ce contrôleur sert uniquement pour la documentation Swagger
    // Les annotations OpenAPI sont définies ci-dessus
}