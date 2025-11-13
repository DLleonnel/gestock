<?php
// config/db.php
// Configuration de la connexion WAMP par défaut
$host = 'localhost';
$dbname = 'stock_db'; // Assurez-vous que c'est le nom de votre base de données
$user = 'root'; 
$password = ''; // Mot de passe par défaut pour WAMP est généralement vide

try {
    // Création de l'objet PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    
    // Configurer PDO pour lever des exceptions en cas d'erreur SQL
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Désactiver l'émulation des requêtes préparées (meilleure sécurité)
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); 
} catch (PDOException $e) {
    // Arrêter l'exécution et afficher l'erreur de connexion
    // En production, vous ne devriez pas afficher $e->getMessage() directement
    die("Erreur de connexion à la base de données : " . $e->getMessage()); 
}
?>