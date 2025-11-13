<?php
$mot_de_passe_clair = '#Trafalgar11'; // <-- ⚠️ REMPLACEZ PAR VOTRE VRAI MOT DE PASSE
$hachage = password_hash($mot_de_passe_clair, PASSWORD_DEFAULT);
echo "Hachage généré : <br>";
echo $hachage;
?>