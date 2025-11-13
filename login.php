<?php
// login.php
session_start();
require_once 'config/db.php';

$message_erreur = '';

if (isset($_SESSION['user_id'])) {
    // Si déjà connecté, rediriger vers le tableau de bord
    header('Location: pages/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $mot_de_passe_saisi = $_POST['password'] ?? '';

    // 1. Récupérer l'utilisateur
    $stmt = $pdo->prepare("SELECT id, password FROM utilisateurs WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($utilisateur && password_verify($mot_de_passe_saisi, $utilisateur['password'])) {
        // 2. Connexion réussie
        $_SESSION['user_id'] = $utilisateur['id'];
        
        // 3. Rediriger
        header('Location: pages/dashboard.php');
        exit();
    } else {
        $message_erreur = "Nom d'utilisateur ou mot de passe incorrect.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Connexion - Gestion de Stock</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            /* Assure que la page prend toute la hauteur pour que le login-container puisse centrer */
            min-height: 100vh;
            margin: 0;
            display: flex;
            justify-content: center; /* Centrage horizontal */
            align-items: center; /* Centrage vertical */
            background-color: #f4f7f6; /* Couleur de fond de sécurité */
        }
        /* Pour le login.php, la classe login-container devrait contenir toute la page */
        .login-container {
            /* Surcharge pour s'assurer que le conteneur est la seule chose visible et centrée */
            /* La taille et le style sont définis dans style.css, mais cette structure corrige le centrage */
            width: 100%;
            max-width: 350px; 
            padding: 20px;
            background-color: white;
            box-shadow: 0 0 25px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Connexion Administrateur</h2>
        <?php if ($message_erreur): ?>
            <p style="color: red;"><?php echo $message_erreur; ?></p>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <label for="username">Nom d'utilisateur:</label>
            <input type="text" id="username" name="username" required><br><br>
            
            <label for="password">Mot de passe:</label>
            <input type="password" id="password" name="password" required><br><br>
            
            <button type="submit">Se connecter</button>
        </form>
    </div>
</body>
</html>