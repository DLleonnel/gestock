<?php
// pages/supprimer_produit.php
session_start();
require_once '../config/db.php';

// ------------------------------------
// 1. Vérification de l'Authentification
// ------------------------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// ------------------------------------
// 2. Récupération de l'ID
// ------------------------------------
$produit_id = (int)($_GET['id'] ?? 0);

if ($produit_id <= 0) {
    // ID invalide, retour au tableau de bord
    header('Location: dashboard.php');
    exit();
}

$message = '';
$base_path = '../'; // Chemin racine pour accéder au dossier 'uploads'

try {
    // 3. Récupérer le chemin de l'image AVANT de supprimer le produit
    $stmt_image = $pdo->prepare("SELECT image_path, nom FROM produits WHERE id = :id");
    $stmt_image->execute(['id' => $produit_id]);
    $produit_a_supprimer = $stmt_image->fetch(PDO::FETCH_ASSOC);

    if ($produit_a_supprimer) {
        $pdo->beginTransaction();

        // 4. Supprimer l'image physique sur le serveur
        $image_path = $produit_a_supprimer['image_path'];
        if ($image_path && file_exists($base_path . $image_path)) {
            unlink($base_path . $image_path);
        }

        // 5. Supprimer le produit de la table 'produits'
        $stmt_delete_produit = $pdo->prepare("DELETE FROM produits WHERE id = :id");
        $stmt_delete_produit->execute(['id' => $produit_id]);

        // 6. Supprimer les transactions associées (Nettoyage de l'historique)
        // Ceci est important pour maintenir la propreté de la base de données
        $stmt_delete_trans = $pdo->prepare("DELETE FROM transactions WHERE produit_id = :id");
        $stmt_delete_trans->execute(['id' => $produit_id]);

        $pdo->commit();
        $message = "Produit '{$produit_a_supprimer['nom']}' et ses transactions supprimés avec succès.";

    } else {
        $message = "Produit introuvable.";
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    $message = "Erreur lors de la suppression : " . $e->getMessage();
}

// Rediriger vers le tableau de bord avec le message (en utilisant une session flash si vous en aviez)
// Pour l'instant, on fait une simple redirection
header('Location: dashboard.php?message=' . urlencode($message)); 
exit();
?>