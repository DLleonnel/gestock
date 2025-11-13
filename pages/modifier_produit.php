<?php
// pages/modifier_produit.php
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
$produit = null;
$produit_id = (int)($_GET['id'] ?? 0); 
$ancienne_quantite_totale = 0; // Utilisé pour le calcul de l'historique

// ------------------------------------
// 2. Récupération des données actuelles du produit et de ses variantes
// ------------------------------------
if ($produit_id > 0) {
    // Requête pour obtenir les infos de base et la quantité totale calculée
    $sql = "SELECT 
                p.id, p.nom, p.couleurs, p.image_path, p.caracteristique_stockage,
                SUM(v.quantite) AS quantite_totale
            FROM 
                produits p
            LEFT JOIN
                produit_variantes v ON p.id = v.produit_id
            WHERE 
                p.id = :id
            GROUP BY
                p.id, p.nom, p.couleurs, p.image_path, p.caracteristique_stockage";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $produit_id]);
        $produit = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$produit) {
            $message = "Erreur: Produit introuvable.";
            $produit_id = 0;
        } else {
            $ancienne_quantite_totale = (int)$produit['quantite_totale'];
            
            // Charger les variantes spécifiques pour le formulaire
            $stmt_variantes = $pdo->prepare("SELECT id, nom_variante, quantite FROM produit_variantes WHERE produit_id = :id ORDER BY id ASC");
            $stmt_variantes->execute(['id' => $produit_id]);
            $produit['variantes'] = $stmt_variantes->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $message = "Erreur base de données lors du chargement: " . $e->getMessage();
        $produit_id = 0;
    }
} else {
    $message = "ID de produit manquant ou invalide.";
}


// ------------------------------------
// 3. Traitement du Formulaire de Modification (Variantes)
// ------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $produit_id > 0) {
    $nom = trim($_POST['nom'] ?? '');
    $couleurs = trim($_POST['couleurs'] ?? '');
    $nouvelles_variantes = $_POST['variantes'] ?? []; // Tableau des variantes existantes modifiées
    $nouvelles_variantes_ajout = $_POST['nouvelles_variantes'] ?? []; // Tableau des nouvelles variantes

    $nouveau_path = $produit['image_path']; // Garder l'ancien chemin par défaut
    $nouvelle_quantite_totale = 0;

    if (empty($nom)) {
        $message = "Veuillez remplir le nom du produit.";
    } else {
        $pdo->beginTransaction();
        try {
            // A. GESTION DE L'IMAGE (Téléversement du nouveau fichier - code de l'utilisateur conservé)
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                if ($produit['image_path'] && file_exists('../' . $produit['image_path'])) {
                    @unlink('../' . $produit['image_path']); // Utilisation de @ pour éviter les avertissements
                }

                $file_tmp_path = $_FILES['image']['tmp_name'];
                $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $new_file_name = uniqid() . '.' . $file_extension;
                $dest_path = '../uploads/' . $new_file_name;

                if (move_uploaded_file($file_tmp_path, $dest_path)) {
                    $nouveau_path = 'uploads/' . $new_file_name;
                } else {
                    throw new Exception("Erreur lors du téléversement de l'image.");
                }
            }

            // B. MISE À JOUR DU PRODUIT (Nom, Couleurs, Image Path)
            // Note: 'quantite' n'est plus mise à jour ici car elle n'existe plus.
            $stmt_update = $pdo->prepare("UPDATE produits SET nom = :nom, couleurs = :couleurs, image_path = :image_path WHERE id = :id");
            $stmt_update->execute([
                'nom' => $nom,
                'couleurs' => $couleurs,
                'image_path' => $nouveau_path,
                'id' => $produit_id
            ]);

            // C. MISE À JOUR/SUPPRESSION DES VARIANTES EXISTANTES
            $stmt_update_var = $pdo->prepare("UPDATE produit_variantes SET nom_variante = :nom, quantite = :qte WHERE id = :id AND produit_id = :produit_id");
            $stmt_delete_var = $pdo->prepare("DELETE FROM produit_variantes WHERE id = :id AND produit_id = :produit_id");

            foreach ($nouvelles_variantes as $var_id => $var) {
                $var_nom = trim($var['nom'] ?? '');
                $var_qte = (int)($var['quantite'] ?? 0);

                if ($var_qte <= 0) {
                    // Supprimer la variante si la quantité est zéro ou moins
                    $stmt_delete_var->execute(['id' => $var_id, 'produit_id' => $produit_id]);
                } else {
                    // Mettre à jour la variante
                    $stmt_update_var->execute([
                        'nom' => $var_nom,
                        'qte' => $var_qte,
                        'id' => $var_id,
                        'produit_id' => $produit_id
                    ]);
                    $nouvelle_quantite_totale += $var_qte;
                }
            }

            // D. AJOUT DE NOUVELLES VARIANTES
            $stmt_insert_var = $pdo->prepare("INSERT INTO produit_variantes (produit_id, nom_variante, quantite) VALUES (:produit_id, :nom, :qte)");
            foreach ($nouvelles_variantes_ajout as $var) {
                $var_nom = trim($var['nom'] ?? '');
                $var_qte = (int)($var['quantite'] ?? 0);

                if (!empty($var_nom) && $var_qte > 0) {
                    $stmt_insert_var->execute([
                        'produit_id' => $produit_id,
                        'nom' => $var_nom,
                        'qte' => $var_qte
                    ]);
                    $nouvelle_quantite_totale += $var_qte;
                }
            }

            // E. MISE À JOUR DE L'HISTORIQUE (Basé sur la différence de stock totale)
            $stock_difference = $nouvelle_quantite_totale - $ancienne_quantite_totale;
            
            if ($stock_difference !== 0) {
                 $stmt_trans = $pdo->prepare("INSERT INTO transactions (produit_id, type_transaction, quantite_modifiee) VALUES (:id, :type, :quantite)");
                 $stmt_trans->execute([
                    'id' => $produit_id, 
                    'type' => $stock_difference > 0 ? 'ajout' : 'vente', 
                    'quantite' => abs($stock_difference) // Stocker la valeur absolue
                 ]);
            }
            
            $pdo->commit();
            $message = "Produit **'{$nom}'** mis à jour avec succès !";
            
            // Redirection pour recharger proprement les données avec les nouvelles requêtes (plus sûr)
            header("Location: modifier_produit.php?id={$produit_id}&message=" . urlencode($message));
            exit();

        } catch (Exception $e) { 
            $pdo->rollBack();
            $message = "Erreur de mise à jour: " . $e->getMessage();
        } 
    }
} 
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Modifier Produit</title>
    <link rel="stylesheet" href="../style.css">
    <script>
        let newVariantIndex = 0;
        
        function ajouterNouvelleVariante() {
            const container = document.getElementById('nouvelles_variantes_container');
            const index = newVariantIndex++;

            const div = document.createElement('div');
            div.className = 'variante-group';
            div.innerHTML = `
                <hr style="border-top: 1px dashed #ccc;">
                <label>Nom Variante:</label>
                <input type="text" name="nouvelles_variantes[${index}][nom]" required>
                
                <label>Quantité Initiale:</label>
                <input type="number" name="nouvelles_variantes[${index}][quantite]" min="1" required>
            `;
            container.appendChild(div);
        }
    </script>
</head>
<body>
    <header>
        <h1>Modifier les informations du produit: <?php echo htmlspecialchars($produit['nom'] ?? 'Chargement...'); ?></h1>
        <a href="dashboard.php">Retour au Tableau de Bord</a>
    </header>
    <main>
        <?php if ($message): ?>
            <p style="color: <?php echo strpos($message, 'succès') !== false ? 'green' : 'red'; ?>; font-weight: bold;"><?php echo $message; ?></p>
        <?php endif; ?>

        <?php if ($produit_id > 0 && $produit): ?>
            <form method="POST" action="modifier_produit.php?id=<?php echo $produit['id']; ?>" enctype="multipart/form-data">
                
                <h2>Informations Générales</h2>
                
                <label for="nom">Nom du Produit:</label>
                <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($produit['nom']); ?>" required>
                
                <label for="couleurs">Couleurs (Informations générales):</label>
                <input type="text" id="couleurs" name="couleurs" value="<?php echo htmlspecialchars($produit['couleurs']); ?>">

                <label>Image Actuelle:</label>
                <?php 
                    $base_path = '../';
                    $image_src = $produit['image_path'] ? $base_path . $produit['image_path'] : $base_path . 'uploads/placeholder.png'; 
                ?>
                <div style="margin-bottom: 10px;">
                    <img src="<?php echo $image_src; ?>" alt="Image produit" style="width: 100px; height: 100px; object-fit: cover; border: 1px solid #ccc;">
                </div>

                <label for="image">Remplacer l'Image:</label>
                <input type="file" id="image" name="image" accept="image/*">
                
                
                <h2 style="margin-top: 30px;">Gestion du Stock par <?php echo htmlspecialchars(ucfirst($produit['caracteristique_stockage'])); ?> (Total: <?php echo $produit['quantite_totale'] ?? 0; ?>)</h2>
                <p style="color: #007bff; font-weight: bold;">Statut: <?php echo htmlspecialchars(ucfirst($produit['caracteristique_stockage'])); ?></p>

                <?php if (!empty($produit['variantes'])): ?>
                    <?php foreach ($produit['variantes'] as $variante): ?>
                        <fieldset style="border: 1px solid #ccc; padding: 10px; margin-bottom: 15px; border-radius: 4px;">
                            <legend style="font-weight: bold; color: #004d99;"><?php echo htmlspecialchars($variante['nom_variante']); ?></legend>
                            
                            <input type="hidden" name="variantes[<?php echo $variante['id']; ?>][id]" value="<?php echo $variante['id']; ?>">

                            <label>Nom de la Variante:</label>
                            <input type="text" name="variantes[<?php echo $variante['id']; ?>][nom]" value="<?php echo htmlspecialchars($variante['nom_variante']); ?>" required>
                            
                            <label>Quantité en Stock (Mettre 0 pour supprimer):</label>
                            <input type="number" name="variantes[<?php echo $variante['id']; ?>][quantite]" value="<?php echo $variante['quantite']; ?>" min="0" required>
                        </fieldset>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: red; font-weight: bold;">Attention: Ce produit n'a aucune variante enregistrée. Veuillez en ajouter une ci-dessous.</p>
                <?php endif; ?>

                <h3 style="margin-top: 25px;">Ajouter de Nouvelles Variantes</h3>
                <div id="nouvelles_variantes_container">
                    </div>
                <button type="button" onclick="ajouterNouvelleVariante()" style="background-color: #5cb85c; width: auto; margin-top: 10px;">+ Ajouter une Nouvelle Variante</button>
                
                <button type="submit">Enregistrer toutes les Modifications</button>
            </form>
        <?php else: ?>
            <p>Impossible d'afficher le formulaire de modification.</p>
        <?php endif; ?>
    </main>
</body>
</html>