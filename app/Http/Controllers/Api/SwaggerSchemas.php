<?php

namespace App\Http\Controllers\Api;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="Utilisateur",
 *     description="Modèle représentant un utilisateur de l'application",
 *     @OA\Property(property="id", type="integer", example=1, description="Identifiant unique de l'utilisateur"),
 *     @OA\Property(property="name", type="string", example="John Doe", description="Nom complet de l'utilisateur"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com", description="Adresse email de l'utilisateur"),
 *     @OA\Property(property="firebase_uid", type="string", nullable=true, example="firebase_uid_123", description="UID Firebase de l'utilisateur"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z", description="Date de création du compte"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z", description="Date de dernière mise à jour")
 * )
 * 
 * @OA\Schema(
 *     schema="SafeZone",
 *     type="object",
 *     title="Zone de Sécurité",
 *     description="Zone privée de sécurité créée par un utilisateur",
 *     @OA\Property(property="id", type="integer", example=1, description="Identifiant unique de la zone"),
 *     @OA\Property(property="name", type="string", example="Maison", description="Nom de la zone de sécurité"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Zone autour de la maison familiale", description="Description de la zone"),
 *     @OA\Property(
 *         property="center",
 *         type="object",
 *         description="Coordonnées du centre de la zone",
 *         @OA\Property(property="lat", type="number", format="float", example=48.8566, description="Latitude"),
 *         @OA\Property(property="lng", type="number", format="float", example=2.3522, description="Longitude")
 *     ),
 *     @OA\Property(property="radius_meters", type="integer", example=100, description="Rayon de la zone en mètres"),
 *     @OA\Property(property="icon_key", type="string", example="home", description="Clé de l'icône à afficher"),
 *     @OA\Property(property="address", type="string", nullable=true, example="123 Rue de la Paix, Paris", description="Adresse de la zone"),
 *     @OA\Property(property="member_ids", type="array", @OA\Items(type="integer"), example={2, 3}, description="IDs des membres assignés à cette zone"),
 *     @OA\Property(property="owner_id", type="integer", example=1, description="ID du propriétaire de la zone"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
 * )
 * 
 * @OA\Schema(
 *     schema="DangerZone",
 *     type="object",
 *     title="Zone de Danger",
 *     description="Zone publique de danger signalée par les utilisateurs",
 *     @OA\Property(property="id", type="integer", example=1, description="Identifiant unique de la zone"),
 *     @OA\Property(property="title", type="string", example="Agression signalée", description="Titre du signalement"),
 *     @OA\Property(property="description", type="string", example="Agression à main armée signalée dans cette zone", description="Description détaillée du danger"),
 *     @OA\Property(
 *         property="center",
 *         type="object",
 *         description="Coordonnées du centre de la zone",
 *         @OA\Property(property="lat", type="number", format="float", example=48.8566, description="Latitude"),
 *         @OA\Property(property="lng", type="number", format="float", example=2.3522, description="Longitude")
 *     ),
 *     @OA\Property(property="radius_m", type="integer", example=50, description="Rayon de la zone en mètres"),
 *     @OA\Property(property="severity", type="string", enum={"low", "medium", "high", "critical"}, example="high", description="Niveau de gravité"),
 *     @OA\Property(property="danger_type", type="string", example="agression", description="Type de danger"),
 *     @OA\Property(property="confirmations", type="integer", example=3, description="Nombre de confirmations reçues"),
 *     @OA\Property(property="reports_count", type="integer", example=1, description="Nombre de signalements d'abus"),
 *     @OA\Property(property="reported_by", type="integer", example=1, description="ID de l'utilisateur qui a signalé"),
 *     @OA\Property(property="last_report_at", type="string", format="date-time", example="2024-01-01T00:00:00Z", description="Date du dernier signalement"),
 *     @OA\Property(property="expires_at", type="string", format="date-time", example="2024-02-01T00:00:00Z", description="Date d'expiration du signalement"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
 * )
 * 
 * @OA\Schema(
 *     schema="Invitation",
 *     type="object",
 *     title="Invitation",
 *     description="Invitation envoyée entre utilisateurs",
 *     @OA\Property(property="id", type="integer", example=1, description="Identifiant unique de l'invitation"),
 *     @OA\Property(property="token", type="string", example="abc123def456", description="Token unique de l'invitation"),
 *     @OA\Property(property="inviter_id", type="integer", example=1, description="ID de l'utilisateur qui invite"),
 *     @OA\Property(property="invitee_email", type="string", format="email", example="invitee@example.com", description="Email de la personne invitée"),
 *     @OA\Property(property="invitee_name", type="string", example="Jane Doe", description="Nom de la personne invitée"),
 *     @OA\Property(property="status", type="string", enum={"pending", "accepted", "declined", "expired"}, example="pending", description="Statut de l'invitation"),
 *     @OA\Property(property="expires_at", type="string", format="date-time", example="2024-01-08T00:00:00Z", description="Date d'expiration de l'invitation"),
 *     @OA\Property(property="accepted_at", type="string", format="date-time", nullable=true, example="2024-01-02T00:00:00Z", description="Date d'acceptation"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
 * )
 * 
 * @OA\Schema(
 *     schema="Relationship",
 *     type="object",
 *     title="Relation",
 *     description="Relation entre deux utilisateurs (proche)",
 *     @OA\Property(property="id", type="integer", example=1, description="Identifiant unique de la relation"),
 *     @OA\Property(property="user_id", type="integer", example=1, description="ID de l'utilisateur principal"),
 *     @OA\Property(property="contact_id", type="integer", example=2, description="ID du contact/proche"),
 *     @OA\Property(property="share_level", type="string", enum={"none", "alerts_only", "real_time"}, example="real_time", description="Niveau de partage de localisation"),
 *     @OA\Property(property="nickname", type="string", nullable=true, example="Papa", description="Surnom donné au contact"),
 *     @OA\Property(property="is_emergency_contact", type="boolean", example=false, description="Contact d'urgence"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T00:00:00Z"),
 *     @OA\Property(property="contact", ref="#/components/schemas/User", description="Informations du contact")
 * )
 * 
 * @OA\Schema(
 *     schema="UserActivity",
 *     type="object",
 *     title="Activité Utilisateur",
 *     description="Historique des activités d'un utilisateur",
 *     @OA\Property(property="id", type="integer", example=1, description="Identifiant unique de l'activité"),
 *     @OA\Property(property="user_id", type="integer", example=1, description="ID de l'utilisateur"),
 *     @OA\Property(property="action", type="string", example="login", description="Type d'action effectuée"),
 *     @OA\Property(property="description", type="string", example="Connexion réussie", description="Description de l'activité"),
 *     @OA\Property(property="ip_address", type="string", nullable=true, example="192.168.1.1", description="Adresse IP"),
 *     @OA\Property(property="user_agent", type="string", nullable=true, example="Mozilla/5.0...", description="User agent du navigateur/app"),
 *     @OA\Property(property="metadata", type="object", nullable=true, description="Données supplémentaires en JSON"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T00:00:00Z")
 * )
 * 
 * @OA\Schema(
 *     schema="ApiResponse",
 *     type="object",
 *     title="Réponse API Standard",
 *     description="Format de réponse standard de l'API",
 *     @OA\Property(property="success", type="boolean", example=true, description="Indique si la requête a réussi"),
 *     @OA\Property(property="data", type="object", nullable=true, description="Données de la réponse"),
 *     @OA\Property(property="message", type="string", nullable=true, example="Opération réussie", description="Message informatif"),
 *     @OA\Property(
 *         property="error",
 *         type="object",
 *         nullable=true,
 *         description="Informations d'erreur",
 *         @OA\Property(property="code", type="string", example="VALIDATION_ERROR", description="Code d'erreur"),
 *         @OA\Property(property="message", type="string", example="Données invalides", description="Message d'erreur"),
 *         @OA\Property(property="details", type="object", nullable=true, description="Détails supplémentaires")
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="PaginatedResponse",
 *     type="object",
 *     title="Réponse Paginée",
 *     description="Format de réponse pour les données paginées",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(property="data", type="array", @OA\Items(type="object"), description="Éléments de la page courante"),
 *         @OA\Property(property="current_page", type="integer", example=1, description="Page courante"),
 *         @OA\Property(property="last_page", type="integer", example=5, description="Dernière page"),
 *         @OA\Property(property="per_page", type="integer", example=15, description="Éléments par page"),
 *         @OA\Property(property="total", type="integer", example=75, description="Nombre total d'éléments"),
 *         @OA\Property(property="from", type="integer", example=1, description="Index du premier élément"),
 *         @OA\Property(property="to", type="integer", example=15, description="Index du dernier élément")
 *     )
 * )
 */
class SwaggerSchemas
{
    // Ce fichier contient uniquement les définitions de schémas Swagger
}