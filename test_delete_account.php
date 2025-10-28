<?php
/**
 * Script de test pour vérifier la fonctionnalité de suppression de compte
 * Simule le comportement de l'application Flutter
 */

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;

// Configuration
$baseUrl = 'http://127.0.0.1:8000/api';
$client = new Client();

echo "=== Test de suppression de compte ===\n\n";

try {
    // 1. Créer un utilisateur de test
    echo "1. Création d'un utilisateur de test...\n";
    
    $registerResponse = $client->post($baseUrl . '/auth/register', [
        'json' => [
            'name' => 'Test Delete User Flutter',
            'email' => 'test-flutter-delete@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'firebase_uid' => 'test-flutter-uid-' . uniqid()
        ],
        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]
    ]);
    
    $registerData = json_decode($registerResponse->getBody(), true);
    
    if (!$registerData['success']) {
        throw new Exception('Échec de l\'inscription: ' . $registerData['message']);
    }
    
    $token = $registerData['data']['token'];
    $userId = $registerData['data']['user']['id'];
    
    echo "✓ Utilisateur créé avec succès (ID: $userId)\n";
    echo "✓ Token obtenu: " . substr($token, 0, 20) . "...\n\n";
    
    // 2. Vérifier que l'utilisateur peut accéder à son profil
    echo "2. Vérification de l'accès au profil...\n";
    
    $profileResponse = $client->get($baseUrl . '/me', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ]
    ]);
    
    $profileData = json_decode($profileResponse->getBody(), true);
    echo "✓ Profil accessible: " . $profileData['name'] . "\n\n";
    
    // 3. Supprimer le compte (comme le fait ProfileRepository)
    echo "3. Suppression du compte...\n";
    
    $deleteResponse = $client->delete($baseUrl . '/user/account', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]
    ]);
    
    $deleteData = json_decode($deleteResponse->getBody(), true);
    
    if ($deleteData['success']) {
        echo "✓ Compte supprimé avec succès: " . $deleteData['message'] . "\n\n";
    } else {
        throw new Exception('Échec de la suppression: ' . $deleteData['message']);
    }
    
    // 4. Vérifier que l'utilisateur ne peut plus accéder à son profil
    echo "4. Vérification que l'accès est révoqué...\n";
    
    try {
        $client->get($baseUrl . '/me', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ]
        ]);
        echo "✗ ERREUR: L'utilisateur peut encore accéder à son profil\n";
    } catch (GuzzleHttp\Exception\ClientException $e) {
        if ($e->getResponse()->getStatusCode() === 401) {
            echo "✓ Accès correctement révoqué (401 Unauthorized)\n";
        } else {
            echo "✗ Erreur inattendue: " . $e->getResponse()->getStatusCode() . "\n";
        }
    }
    
    echo "\n=== Test terminé avec succès ===\n";
    
} catch (Exception $e) {
    echo "✗ ERREUR: " . $e->getMessage() . "\n";
    exit(1);
}