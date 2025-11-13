<?php
// logout.php

// 1. Démarrer la session (nécessaire pour accéder aux variables de session)
session_start();

// 2. Supprimer toutes les variables de session
// Ceci supprime l'ID de l'utilisateur stocké
$_SESSION = array();

// 3. Si vous voulez détruire complètement la session, 
// vous pouvez utiliser la fonction session_destroy().
// Cela supprime le fichier de session sur le serveur.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 4. Rediriger l'utilisateur vers la page de connexion
header("Location: login.php");
exit;
?>