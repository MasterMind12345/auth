<?php
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $salle_id = $_POST['salle_id'];
    $cours_id = $_POST['cours_id'];
    $jour = $_POST['jour'];
    $heure_debut = $_POST['heure_debut'];
    $heure_fin = $_POST['heure_fin'];
    $enseignant_id = $_POST['enseignant_id'];
    
    try {
        // Ajouter directement la séance sans vérification de conflit
        $stmt = $pdo->prepare("INSERT INTO seances (cours_id, salle_id, jour, heure_debut, heure_fin, enseignant_id) 
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$cours_id, $salle_id, $jour, $heure_debut, $heure_fin, $enseignant_id]);
        
        header("Location: salle_detail.php?id=$salle_id&success=Séance ajoutée avec succès");
        exit;
    } catch (PDOException $e) {
        header("Location: salle_detail.php?id=$salle_id&error=Erreur lors de l'ajout de la séance");
        exit;
    }
}

header("Location: index.php");
exit;