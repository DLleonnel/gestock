<?php
// actions/supprimer_definitivement.php
session_start();
require_once '../config/db.php';

// Vérification de l'authentification
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$produit_id = (int)($_GET['id'] ?? 0);
$message = ''; // Initialiser le message

if ($produit_id > 0) {
    // Utiliser une transaction pour garantir que toutes les tables liées sont nettoyées
    $pdo->beginTransaction();
    try {
        // 1. Supprimer les transactions liées
        $stmt_trans = $pdo->prepare("DELETE FROM transactions WHERE produit_id = :id");
        $stmt_trans->execute(['id' => $produit_id]);
        
        // 2. Supprimer les variantes liées
        $stmt_var = $pdo->prepare("DELETE FROM produit_variantes WHERE produit_id = :id");
        $stmt_var->execute(['id' => $produit_id]);
        
        // 3. Supprimer le produit lui-même
        // Sécurité: supprime UNIQUEMENT si le produit est marqué comme soft-deleted
        $stmt_prod = $pdo->prepare("DELETE FROM produits WHERE id = :id AND supprime = TRUE"); 
        $stmt_prod->execute(['id' => $produit_id]);
        
        // Vérifier si une ligne a été affectée (pour s'assurer que le produit existait et était archivé)
        if ($stmt_prod->rowCount() > 0) {
            $pdo->commit();
            $message = "Produit et toutes les données associées SUPPRIMÉS DÉFINITIVEMENT avec succès.";
        } else {
            // Si rowCount() est 0, le produit n'était pas archivé (ou n'existait pas)
            // On peut considérer cela comme une erreur de sécurité ou une tentative de suppression invalide
            $pdo->rollBack();
            $message = "Erreur: Le produit n'a pas pu être supprimé définitivement. Il n'était peut-être pas archivé.";
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Erreur fatale lors de la suppression définitive: " . $e->getMessage();
    }
} else {
    $message = "ID de produit invalide pour la suppression définitive.";
}

// Redirection finale vers le tableau de bord avec le message
header('Location: ../pages/dashboard.php?message=' . urlencode($message));
exit();
?>