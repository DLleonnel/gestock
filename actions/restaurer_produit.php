<?php
// actions/restaurer_produit.php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$produit_id = (int)($_GET['id'] ?? 0);

if ($produit_id > 0) {
    try {
        // Mettre à jour la colonne 'supprime' à FALSE pour restaurer le produit
        $stmt = $pdo->prepare("UPDATE produits SET supprime = FALSE WHERE id = :id");
        $stmt->execute(['id' => $produit_id]);

        $message = "Produit restauré avec succès ! Il est de nouveau visible dans l'inventaire principal.";
        // Redirection vers le tableau de bord
        header('Location: ../pages/dashboard.php?message=' . urlencode($message));
        exit();

    } catch (PDOException $e) {
        $message = "Erreur lors de la restauration du produit: " . $e->getMessage();
        header('Location: ../pages/dashboard.php?message=' . urlencode($message));
        exit();
    }
} else {
    $message = "ID de produit invalide.";
    header('Location: ../pages/dashboard.php?message=' . urlencode($message));
    exit();
}
?>