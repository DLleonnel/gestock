<?php
// pages/historique.php
session_start();
require_once '../config/db.php';

// ------------------------------------
// 1. Vérification de l'Authentification
// ------------------------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$transactions = [];
$error_message = '';

// ------------------------------------
// 2. Récupération des Transactions
// ------------------------------------
try {
    // Jointure (JOIN) entre la table 'transactions' et 'produits' pour obtenir le nom du produit
    $sql = "SELECT 
                t.date_transaction, 
                t.type_transaction, 
                t.quantite_modifiee,
                p.nom AS nom_produit
            FROM 
                transactions t
            JOIN 
                produits p ON t.produit_id = p.id
            ORDER BY 
                t.date_transaction DESC"; // Afficher les plus récentes en premier
                
    $stmt = $pdo->query($sql);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Erreur lors du chargement de l'historique : " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Historique des Transactions</title>
    <link rel="stylesheet" href="../style.css"> 
</head>
<body>
    <header>
        <h1>Historique des Transactions</h1>
        <a href="dashboard.php">Retour au Tableau de Bord</a>
    </header>
    <main>
        <?php if ($error_message): ?>
            <p style="color: red; font-weight: bold;"><?php echo $error_message; ?></p>
        <?php endif; ?>

        <h2>Toutes les Entrées et Sorties de Stock</h2>

        <?php if (empty($transactions)): ?>
            <p>Aucune transaction enregistrée pour le moment. L'historique se remplira lorsque vous ajouterez ou vendrez des produits.</p>
        <?php else: ?>
            <table border="1" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>Date et Heure</th>
                        <th>Produit</th>
                        <th>Type d'Opération</th>
                        <th>Quantité Modifiée</th>
                        <th>Impact sur le Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                        <?php
                            $quantite = $transaction['quantite_modifiee'];
                            $type = htmlspecialchars($transaction['type_transaction']);
                            $nom_produit = htmlspecialchars($transaction['nom_produit']);
                            
                            // Définir la couleur et le signe en fonction de la quantité
                            if ($quantite > 0) {
                                $class_impact = 'color: green; font-weight: bold;';
                                $signe = '+';
                                $type_display = 'Ajout/Retour';
                            } else {
                                $class_impact = 'color: red; font-weight: bold;';
                                $signe = ''; // La quantité est déjà négative pour les ventes
                                $type_display = 'Vente/Sortie';
                            }
                        ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($transaction['date_transaction'])); ?></td>
                            <td><?php echo $nom_produit; ?></td>
                            <td><?php echo $type_display; ?></td>
                            <td style="text-align: center; <?php echo $class_impact; ?>">
                                <?php echo $signe . abs($quantite); ?>
                            </td>
                            <td style="text-align: center; <?php echo $class_impact; ?>">
                                <?php echo $quantite > 0 ? '↗️ Augmentation' : '↘️ Diminution'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>