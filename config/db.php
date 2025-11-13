<?php
// config/db.php

// Récupération des variables d'environnement pour l'hébergement cloud (ex: Render, PlanetScale)
// Ces variables DOIVENT être définies dans l'environnement de production.
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'stock_db'; 
$user = getenv('DB_USER') ?: 'root'; 
$password = getenv('DB_PASS') ?: ''; // En local (WAMP/XAMPP)
$port = getenv('DB_PORT') ?: '3306'; // Port standard MySQL

// --- Vérification et Connexion ---

try {
    // Si nous sommes en production (variables d'environnement définies), 
    // l'hôte sera celui de PlanetScale, et le port peut être nécessaire.
    // Sinon, nous utilisons localhost.
    
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    // Création de l'objet PDO
    $pdo = new PDO($dsn, $user, $password);
    
    // Configurer PDO pour lever des exceptions en cas d'erreur SQL
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Désactiver l'émulation des requêtes préparées (meilleure sécurité)
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); 
    
} catch (PDOException $e) {
    // Message à afficher. 
    // En production, affichez un message générique pour des raisons de sécurité.
    if ($host === 'localhost') {
        // Afficher le message d'erreur complet pour le débogage local
        die("Erreur de connexion à la base de données locale : " . $e->getMessage()); 
    } else {
        // Message générique en production
        die("Service indisponible. Impossible de se connecter à la base de données."); 
    }
}
?>