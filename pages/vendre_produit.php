<?php
// pages/vendre_produit.php
session_start();
require_once '../config/db.php';

// ------------------------------------
// 1. Vérification de l'Authentification
// ------------------------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$message = '';
$produits_avec_variantes = [];

// ------------------------------------
// 2. Récupération des Produits et de leurs Variantes en Stock
// ------------------------------------
try {
    // 1. Récupérer tous les produits avec leur quantité totale (calculée par la somme des variantes)
    $sql_produits = "SELECT 
                        p.id, 
                        p.nom, 
                        p.caracteristique_stockage, 
                        COALESCE(SUM(v.quantite), 0) AS quantite_totale
                    FROM 
                        produits p
                    LEFT JOIN 
                        produit_variantes v ON p.id = v.produit_id
                    GROUP BY 
                        p.id, p.nom, p.caracteristique_stockage
                    HAVING 
                        quantite_totale > 0 /* Seulement les produits qui ont du stock */
                    ORDER BY 
                        p.nom ASC";
    
    $stmt_produits = $pdo->query($sql_produits);
    $produits_en_stock = $stmt_produits->fetchAll(PDO::FETCH_ASSOC);

    // 2. Pour chaque produit, récupérer les détails de ses variantes
    foreach ($produits_en_stock as $produit) {
        $produit_id = $produit['id'];
        
        // Charger les variantes disponibles (quantité > 0)
        $stmt_variantes = $pdo->prepare("SELECT id, nom_variante, quantite FROM produit_variantes WHERE produit_id = :id AND quantite > 0 ORDER BY nom_variante ASC");
        $stmt_variantes->execute(['id' => $produit_id]);
        $produit['variantes'] = $stmt_variantes->fetchAll(PDO::FETCH_ASSOC);

        // N'ajouter que si des variantes sont trouvées (ou si la quantité totale est gérée comme une "variante standard")
        if (!empty($produit['variantes'])) {
            $produits_avec_variantes[] = $produit;
        }
    }

} catch (PDOException $e) {
    $message = "Erreur lors du chargement des produits : " . $e->getMessage();
    $produits_avec_variantes = [];
}


// ------------------------------------
// 3. Traitement du Formulaire de Vente
// ------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $produit_id = (int)($_POST['produit_id'] ?? 0);
    $quantite_vendue = (int)($_POST['quantite_vendue'] ?? 0);
    $variante_id = (int)($_POST['variante_id'] ?? 0); // Nouveau: ID de la variante sélectionnée

    if ($produit_id <= 0 || $quantite_vendue <= 0 || $variante_id <= 0) {
        $message = "Veuillez sélectionner un produit, une variante, et spécifier une quantité valide.";
    } else {
        $pdo->beginTransaction();

        try {
            // A. Vérifier le stock disponible pour cette VARIANTE
            $stmt_check = $pdo->prepare("SELECT v.quantite, p.nom, v.nom_variante 
                                         FROM produit_variantes v 
                                         JOIN produits p ON v.produit_id = p.id
                                         WHERE v.id = :variante_id AND v.produit_id = :produit_id");
            $stmt_check->execute(['variante_id' => $variante_id, 'produit_id' => $produit_id]);
            $details_vente = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if (!$details_vente) {
                throw new Exception("Variante ou produit introuvable.");
            }
            $stock_actuel = (int)$details_vente['quantite'];
            
            if ($quantite_vendue > $stock_actuel) {
                throw new Exception("Stock insuffisant pour la variante '{$details_vente['nom_variante']}'. Stock actuel : {$stock_actuel}.");
            }
            
            // B. Mettre à jour la Quantité dans la table 'produit_variantes' (Décrémenter le stock)
            $stmt_update = $pdo->prepare("UPDATE produit_variantes SET quantite = quantite - :quantite_vendue WHERE id = :variante_id");
            $stmt_update->execute(['quantite_vendue' => $quantite_vendue, 'variante_id' => $variante_id]);

            // C. Enregistrer la Transaction (dans transactions)
            $quantite_pour_historique = -$quantite_vendue; 
            
            $stmt_insert = $pdo->prepare("INSERT INTO transactions (produit_id, type_transaction, quantite_modifiee, variante_id) VALUES (:produit_id, 'vente', :quantite_modifiee, :variante_id)");
            $stmt_insert->execute([
                'produit_id' => $produit_id,
                'quantite_modifiee' => $quantite_pour_historique,
                'variante_id' => $variante_id
            ]);

            $pdo->commit();
            $message = "Vente de **{$quantite_vendue} {$details_vente['nom']} ({$details_vente['nom_variante']})** enregistrée avec succès !";
            
            // Redirection vers le dashboard pour recharger l'inventaire
            header('Location: dashboard.php?message=' . urlencode($message));
            exit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Erreur lors de l'enregistrement de la vente: " . $e->getMessage();
        }
    }
    // Recharger la liste des produits après une erreur pour garder le formulaire à jour
    // (Une solution plus propre serait de re-exécuter le bloc 2)
    header('Location: vendre_produit.php?message=' . urlencode($message));
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Enregistrer une Vente</title>
    <link rel="stylesheet" href="../style.css">
    <script>
        // Fonction JavaScript pour afficher les variantes disponibles après la sélection du produit
        function chargerVariantes() {
            const produitSelect = document.getElementById('produit_select');
            const varianteSelect = document.getElementById('variante_select');
            const selectedOption = produitSelect.options[produitSelect.selectedIndex];
            const variantesData = JSON.parse(selectedOption.dataset.variantes || '[]');
            const typeStock = selectedOption.dataset.typeStock || 'Variante';

            varianteSelect.innerHTML = ''; // Nettoyer les options précédentes
            
            if (variantesData.length > 0) {
                const defaultOption = document.createElement('option');
                defaultOption.value = "";
                defaultOption.textContent = `-- Choisir une ${typeStock} --`;
                defaultOption.disabled = true;
                defaultOption.selected = true;
                varianteSelect.appendChild(defaultOption);

                variantesData.forEach(variante => {
                    const option = document.createElement('option');
                    option.value = variante.id;
                    option.textContent = `${variante.nom_variante} (Stock: ${variante.quantite})`;
                    varianteSelect.appendChild(option);
                });
                varianteSelect.disabled = false;
            } else {
                 const option = document.createElement('option');
                 option.value = "";
                 option.textContent = "Aucune variante en stock.";
                 option.disabled = true;
                 option.selected = true;
                 varianteSelect.appendChild(option);
                 varianteSelect.disabled = true;
            }
        }
        
        window.onload = chargerVariantes; // Appeler au chargement initial
    </script>
</head>
<body>
    <header>
        <h1>Enregistrer une Vente (Sortie de Stock)</h1>
        <a href="dashboard.php">Retour au Tableau de Bord</a>
    </header>
    <main>
        <?php if ($message): ?>
            <p style="color: <?php echo strpos($message, 'succès') !== false ? 'green' : 'red'; ?>; font-weight: bold;"><?php echo $message; ?></p>
        <?php endif; ?>

        <?php if (empty($produits_avec_variantes)): ?>
            <p style="color: blue;">Aucun produit avec du stock disponible pour enregistrer une vente.</p>
        <?php else: ?>
            <form method="POST" action="vendre_produit.php">
                
                <label for="produit_select">Produit Vendu:</label>
                <select id="produit_select" name="produit_id" onchange="chargerVariantes()" required>
                    <option value="">-- Sélectionnez un produit --</option>
                    <?php foreach ($produits_avec_variantes as $produit): ?>
                        <?php 
                            // Encoder les variantes en JSON pour le JavaScript
                            $variantes_json = htmlspecialchars(json_encode($produit['variantes']), ENT_QUOTES, 'UTF-8');
                        ?>
                        <option 
                            value="<?php echo $produit['id']; ?>"
                            data-variantes="<?php echo $variantes_json; ?>"
                            data-type-stock="<?php echo htmlspecialchars(ucfirst($produit['caracteristique_stockage'])); ?>"
                        >
                            <?php echo htmlspecialchars($produit['nom']); ?> (Total: <?php echo $produit['quantite_totale']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <label for="variante_select">Variante à Vendre (<?php echo htmlspecialchars(ucfirst($produit['caracteristique_stockage'] ?? 'Variante')); ?>):</label>
                <select id="variante_select" name="variante_id" required disabled>
                     <option value="" disabled selected>Sélectionnez d'abord un produit</option>
                </select>
                
                <label for="quantite_vendue">Quantité Vendue:</label>
                <input type="number" id="quantite_vendue" name="quantite_vendue" required min="1">
                
                <button type="submit">Enregistrer la Vente et Déduire du Stock</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>