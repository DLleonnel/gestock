<?php
// actions/supprimer_produit.php
session_start();
require_once '../config/db.php';

// ------------------------------------
// 1. Vérification de l'Authentification
// ------------------------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$produit_id = (int)($_GET['id'] ?? 0);
$message = '';

if ($produit_id > 0) {
    try {
        // La requête SQL pour le SOFT DELETE:
        // Mettre à jour la colonne 'supprime' à TRUE pour marquer le produit comme archivé
        $stmt = $pdo->prepare("UPDATE produits SET supprime = TRUE WHERE id = :id");
        $stmt->execute(['id' => $produit_id]);

        // Vérification du succès de la mise à jour (au cas où l'ID n'existerait pas)
        if ($stmt->rowCount() > 0) {
            $message = "Produit archivé avec succès ! Il est maintenant dans la liste des Produits Archivés.";
        } else {
            $message = "Erreur: Produit introuvable ou déjà archivé.";
        }
        

    } catch (PDOException $e) {
        $message = "Erreur SQL lors de l'archivage: " . $e->getMessage();
    }
} else {
    $message = "ID de produit invalide pour l'archivage.";
}

// Redirection vers le tableau de bord avec le message
header('Location: ../pages/dashboard.php?message=' . urlencode($message));
exit();
?>