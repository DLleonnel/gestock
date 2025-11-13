<?php
// pages/ajouter_produit.php (Modifié pour variantes)
session_start();
require_once '../config/db.php';

// ... (Vérification de l'authentification) ...
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$message = '';

// ------------------------------------
// 2. Traitement du Formulaire
// ------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $type_stockage = $_POST['type_stockage'] ?? 'simple'; // Nouvelle variable
    $variantes = $_POST['variantes'] ?? []; // Tableau des variantes (noms et quantités)
    $image_path = null;

    // Calculer la quantité totale initiale pour l'historique
    $quantite_totale_initiale = 0;
    foreach ($variantes as $v) {
        $quantite_totale_initiale += (int)($v['quantite'] ?? 0);
    }
    
    // Validation des données
    if (empty($nom) || ($type_stockage !== 'simple' && empty($variantes))) {
        $message = "Veuillez remplir le nom et définir au moins une variante avec quantité.";
    } else {
        // GESTION DE L'IMAGE... (Le code reste le même)
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            // ... (Code de téléversement et de stockage de $image_path) ...
            $file_tmp_path = $_FILES['image']['tmp_name'];
            $file_name = $_FILES['image']['name'];
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_file_name = uniqid() . '.' . $file_extension;
            $upload_directory = '../uploads/';
            $dest_path = $upload_directory . $new_file_name;

            if (!is_dir($upload_directory)) { mkdir($upload_directory, 0777, true); }

            if (move_uploaded_file($file_tmp_path, $dest_path)) {
                $image_path = 'uploads/' . $new_file_name; 
            } else {
                $message = "Erreur lors du téléversement de l'image.";
            }
        }
        
        // INSERTION EN BASE DE DONNÉES
        if (empty($message)) {
            $pdo->beginTransaction(); // Début de la transaction pour les deux tables

            try {
                // 1. Insertion dans la table 'produits'
                $stmt = $pdo->prepare("INSERT INTO produits (nom, caracteristique_stockage, couleurs, image_path) VALUES (:nom, :type_stockage, :couleurs, :image_path)");
                $stmt->execute([
                    'nom' => $nom,
                    'type_stockage' => $type_stockage,
                    'couleurs' => $_POST['couleurs'] ?? '', // Garder cette colonne pour l'info générale
                    'image_path' => $image_path 
                ]);
                $produit_id = $pdo->lastInsertId();

                // 2. Insertion dans la table 'produit_variantes'
                if ($quantite_totale_initiale > 0) {
                    $stmt_var = $pdo->prepare("INSERT INTO produit_variantes (produit_id, nom_variante, quantite) VALUES (:produit_id, :nom_variante, :quantite)");
                    
                    if ($type_stockage === 'simple') {
                        // Cas où l'utilisateur a juste saisi la quantité dans le premier champ (simple)
                        $stmt_var->execute(['produit_id' => $produit_id, 'nom_variante' => 'Standard', 'quantite' => (int)$_POST['quantite_simple']]);

                        // Enregistrement dans l'historique (utilise la quantité simple)
                        $stmt_trans = $pdo->prepare("INSERT INTO transactions (produit_id, type_transaction, quantite_modifiee) VALUES (:id, 'ajout', :quantite)");
                        $stmt_trans->execute(['id' => $produit_id, 'quantite' => (int)$_POST['quantite_simple']]);
                    
                    } else {
                        // Cas où l'utilisateur a saisi les variantes (couleurs, capacités, etc.)
                        foreach ($variantes as $variante) {
                            $var_nom = trim($variante['nom'] ?? '');
                            $var_qte = (int)($variante['quantite'] ?? 0);
                            
                            if (!empty($var_nom) && $var_qte > 0) {
                                $stmt_var->execute([
                                    'produit_id' => $produit_id, 
                                    'nom_variante' => $var_nom, 
                                    'quantite' => $var_qte
                                ]);
                            }
                        }

                        // Enregistrement dans l'historique (utilise la quantité totale des variantes)
                        $stmt_trans = $pdo->prepare("INSERT INTO transactions (produit_id, type_transaction, quantite_modifiee) VALUES (:id, 'ajout', :quantite)");
                        $stmt_trans->execute(['id' => $produit_id, 'quantite' => $quantite_totale_initiale]);
                    }
                }


                $pdo->commit();
                $message = "Produit **'{$nom}'** ajouté avec succès avec {$quantite_totale_initiale} unités initiales!";

            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = "Erreur base de données : " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Ajouter un Produit</title>
    <link rel="stylesheet" href="../style.css">
    <script>
        // Fonction JavaScript pour ajouter dynamiquement des champs de variante
        function ajouterVariante() {
            const container = document.getElementById('variantes_container');
            const index = container.children.length;

            const div = document.createElement('div');
            div.className = 'variante-group';
            div.innerHTML = `
                <label for="variante_nom_${index}">Nom de la Variante (ex: Rouge, 64GB):</label>
                <input type="text" name="variantes[${index}][nom]" id="variante_nom_${index}" required>
                
                <label for="variante_qte_${index}">Quantité pour cette Variante:</label>
                <input type="number" name="variantes[${index}][quantite]" id="variante_qte_${index}" min="1" required>
            `;
            container.appendChild(div);
        }

        function toggleVariantes() {
            const select = document.getElementById('type_stockage');
            const simpleQte = document.getElementById('simple_quantite_group');
            const varianteQte = document.getElementById('variantes_gestion_group');

            if (select.value === 'simple') {
                simpleQte.style.display = 'block';
                varianteQte.style.display = 'none';
            } else {
                simpleQte.style.display = 'none';
                varianteQte.style.display = 'block';
            }
        }

        // Ajout d'une variante par défaut au chargement
        window.onload = function() {
            toggleVariantes();
            // Si l'utilisateur choisit le mode variante, on ajoute un champ par défaut
            if(document.getElementById('type_stockage').value !== 'simple' && document.getElementById('variantes_container').children.length === 0) {
                 ajouterVariante();
            }
        };
    </script>
</head>
<body>
    <header>
        <h1>Ajouter un Nouveau Produit</h1>
        <a href="dashboard.php">Retour au Tableau de Bord</a>
    </header>
    <main>
        <?php if ($message): ?>
            <p style="color: <?php echo strpos($message, 'succès') !== false ? 'green' : 'red'; ?>; font-weight: bold;"><?php echo $message; ?></p>
        <?php endif; ?>

        <form method="POST" action="ajouter_produit.php" enctype="multipart/form-data">
            
            <label for="nom">Nom du Produit:</label>
            <input type="text" id="nom" name="nom" required>
            
            <label for="type_stockage">Méthode de Stockage:</label>
            <select id="type_stockage" name="type_stockage" onchange="toggleVariantes()">
                <option value="simple">Quantité Totale Unique</option>
                <option value="couleur">Par Couleur</option>
                <option value="capacite">Par Capacité (ex: 64GB, 256GB)</option>
                <option value="autre">Autre Variante</option>
            </select>

            <div id="simple_quantite_group">
                <label for="quantite_simple">Quantité Initiale Totale en Stock:</label>
                <input type="number" id="quantite_simple" name="quantite_simple" min="0" value="0">
            </div>

            <div id="variantes_gestion_group" style="display:none; margin-top:20px;">
                <h3>Définition des Variantes</h3>
                <div id="variantes_container">
                    </div>
                <button type="button" onclick="ajouterVariante()">+ Ajouter une Variante</button>
                <p style="margin-top: 15px; color:#555; font-style: italic;">La quantité totale sera la somme des quantités de ces variantes.</p>
            </div>


            <label for="couleurs">Couleurs (Info générale, facultatif):</label>
            <input type="text" id="couleurs" name="couleurs" placeholder="Rouge, Bleu, Noir">

            <label for="image">Image du Produit:</label>
            <input type="file" id="image" name="image" accept="image/*">
            
            <button type="submit">Enregistrer le Produit</button>
        </form>
    </main>
</body>
</html>