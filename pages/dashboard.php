<?php
// pages/dashboard.php
session_start();
require_once '../config/db.php';

// ------------------------------------
// 1. Vérification de l'Authentification (Garde-fou)
// ------------------------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$produits = [];
$produits_archives = [];
$error_message = null;

// ------------------------------------
// 2. Récupération des Produits Actifs (p.supprime = FALSE)
// ------------------------------------
try {
    // La requête principale pour les produits actifs
    $sql_base = "SELECT 
                p.id, 
                p.nom, 
                p.couleurs,
                p.image_path,
                p.caracteristique_stockage, 
                COALESCE(SUM(v.quantite), 0) AS quantite_totale 
            FROM 
                produits p
            LEFT JOIN 
                produit_variantes v ON p.id = v.produit_id";

    // A. Récupération des produits ACTIFS (supprime = FALSE)
    $sql_actifs = $sql_base . " WHERE p.supprime = FALSE GROUP BY p.id ORDER BY p.nom ASC";
    $stmt_actifs = $pdo->query($sql_actifs);
    $produits = $stmt_actifs->fetchAll(PDO::FETCH_ASSOC);

    // B. Récupération des produits ARCHIVÉS (supprime = TRUE)
    $sql_archives = $sql_base . " WHERE p.supprime = TRUE GROUP BY p.id ORDER BY p.nom ASC";
    $stmt_archives = $pdo->query($sql_archives);
    $produits_archives = $stmt_archives->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Erreur lors du chargement des produits : " . $e->getMessage();
}

// Définir le chemin de base pour les images
$base_image_path = '../'; 

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Tableau de Bord - Gestion de Stock</title>
    <link rel="stylesheet" href="../style.css"> 
    <style>
        /* Styles pour les lignes de détails des variantes */
        .details-row td {
            background-color: #f7f7f7;
            padding: 5px 15px;
            border-bottom: 2px solid #ddd;
            font-size: 0.9em;
        }
        .details-row ul {
            list-style: none;
            padding-left: 0;
            margin: 5px 0 0 0;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        /* Style pour les produits archivés */
        .archived-row {
            background-color: #f0f0f0; 
            color: #666;
        }
    </style>
</head>
<body>
    <header>
        <h1>Tableau de Bord de l'Inventaire</h1>
        <a href="../logout.php">Déconnexion</a>
    </header>
    <main>
        <?php if (isset($_GET['message'])): ?>
            <?php 
                $message_text = htmlspecialchars($_GET['message']);
                // Gérer les messages de succès et d'erreur
                $style = (strpos($message_text, 'succès') !== false || strpos($message_text, 'restauré') !== false) ? 'green' : 'red';
            ?>
            <p style="color: <?php echo $style; ?>; font-weight: bold;"><?php echo $message_text; ?></p>
        <?php endif; ?>

        <div class="nav-block cyan-bg">
            <a href="ajouter_produit.php">➕ Ajouter un Produit</a> 
            <a href="vendre_produit.php">💲 Enregistrer une Vente</a> 
            <a href="historique.php">📜 Voir l'Historique</a>
        </div>

        <?php if (isset($error_message)): ?>
            <p style="color: red;"><?php echo $error_message; ?></p>
        <?php endif; ?>

        <h2>Inventaire Actuel</h2>

        <?php if (empty($produits)): ?>
            <p>Aucun produit actif en stock pour le moment. <a href="ajouter_produit.php">Ajoutez-en un !</a></p>
        <?php else: ?>
            <table border="1">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Nom du Produit</th>
                        <th>Type Stock</th> 
                        <th>Quantité Totale</th>
                        <th>Statut</th>
                        <th>Couleurs</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produits as $produit): ?>
                        <?php 
                            $produit_id = $produit['id'];
                            $quantite = (int)$produit['quantite_totale']; 
                            $statut_class = '';
                            $statut_texte = 'En Stock';
                            
                            if ($quantite === 0) {
                                $statut_texte = '🔴 Épuisé';
                                $statut_class = 'status-out-of-stock';
                            } elseif ($quantite < 10) { 
                                $statut_texte = '🟠 Stock Faible';
                                $statut_class = 'status-low-stock';
                            }
                            
                            $image_src = $produit['image_path'] ? $base_image_path . $produit['image_path'] : '../uploads/placeholder.png';
                        ?>
                        
                        <tr class="<?php echo $statut_class; ?>">
                            <td style="text-align: center;">
                                <img src="<?php echo $image_src; ?>" alt="<?php echo htmlspecialchars($produit['nom']); ?>" style="width: 50px; height: 50px; object-fit: cover;">
                            </td>
                            <td><?php echo htmlspecialchars($produit['nom']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($produit['caracteristique_stockage'])); ?></td> 
                            <td style="text-align: center; font-weight: bold;"><?php echo $quantite; ?></td>
                            <td><?php echo $statut_texte; ?></td>
                            <td><?php echo htmlspecialchars($produit['couleurs']); ?></td>
                            <td style="text-align: center;">
                                <a href="modifier_produit.php?id=<?php echo $produit_id; ?>">Modifier</a> |
                                <a href="vendre_produit.php?id=<?php echo $produit_id; ?>">Vendre</a> |
                                <a href="../actions/supprimer_produit.php?id=<?php echo $produit_id; ?>" onclick="return confirm('Êtes-vous sûr de vouloir archiver ce produit? Il restera visible dans la section Archivés.');">Archiver</a>
                            </td>
                        </tr>

                        <?php 
                        // AFFICHAGE DES VARIANTES DÉTAILLÉES 
                        if ($produit['caracteristique_stockage'] !== 'simple' && $quantite > 0): 
                            $stmt_var = $pdo->prepare("SELECT nom_variante, quantite FROM produit_variantes WHERE produit_id = :id AND quantite > 0 ORDER BY nom_variante ASC");
                            $stmt_var->execute(['id' => $produit_id]);
                            $variantes = $stmt_var->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <tr class="details-row">
                            <td colspan="7">
                                <strong>Détails du Stock par <?php echo htmlspecialchars(ucfirst($produit['caracteristique_stockage'])); ?>:</strong>
                                <ul>
                                    <?php foreach ($variantes as $variante): ?>
                                        <li><?php echo htmlspecialchars($variante['nom_variante']); ?>: **<?php echo $variante['quantite']; ?>** unités</li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                        </tr>
                        <?php endif; ?>

                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <hr style="margin: 40px 0;">

        <h2>Produits Archivés (Suppression Douce)</h2>

        <?php if (empty($produits_archives)): ?>
            <p>Aucun produit n'est actuellement archivé.</p>
        <?php else: ?>
            <table border="1">
                <thead>
                     <tr>
                        <th>Nom du Produit</th>
                        <th>Quantité Totale</th>
                        <th>Type Stock</th>
                        <th>Statut</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produits_archives as $produit): ?>
                        <tr class="archived-row">
                            <td><?php echo htmlspecialchars($produit['nom']); ?></td>
                            <td style="text-align: center;"><?php echo $produit['quantite_totale']; ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($produit['caracteristique_stockage'])); ?></td> 
                            <td>Archivé / Supprimé 🔴</td>
                            <td style="text-align: center;">
                                <a href="../actions/restaurer_produit.php?id=<?php echo $produit['id']; ?>" style="font-weight: bold; color: green; margin-right: 10px;">
                                    Restaurer
                                </a>
                                |
                                <a href="../actions/supprimer_definitivement.php?id=<?php echo $produit['id']; ?>" 
                                   onclick="return confirm('ATTENTION : Êtes-vous sûr de vouloir SUPPRIMER DÉFINITIVEMENT ce produit et toutes ses données? Cette action est irréversible.');"
                                   style="color: red; margin-left: 10px;">
                                    Supprimer Définitivement
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>