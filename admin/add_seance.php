<?php
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $salle_id = $_POST['salle_id'];
    $cours_id = $_POST['cours_id'];
    $jour = $_POST['jour'];
    $heure_debut = $_POST['heure_debut'];
    $heure_fin = $_POST['heure_fin'];
    $enseignant_id = $_POST['enseignant_id'];
    $semaine_id = $_POST['semaine_id'];
    
    try {
        // Vérifier si la semaine est valide
        $stmt = $pdo->prepare("SELECT id FROM semaines WHERE id = ?");
        $stmt->execute([$semaine_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Semaine invalide");
        }

        // Vérifier les conflits d'emploi du temps
        $stmt = $pdo->prepare("SELECT id FROM seances 
                              WHERE salle_id = ? 
                              AND semaine_id = ?
                              AND jour = ?
                              AND (
                                  (? BETWEEN heure_debut AND heure_fin)
                                  OR (? BETWEEN heure_debut AND heure_fin)
                                  OR (heure_debut BETWEEN ? AND ?)
                              )");
        $stmt->execute([
            $salle_id,
            $semaine_id,
            $jour,
            $heure_debut,
            $heure_fin,
            $heure_debut,
            $heure_fin
        ]);
        
        if ($stmt->fetch()) {
            throw new Exception("Conflit d'emploi du temps : une séance existe déjà pour cette plage horaire");
        }

        // Ajouter la séance avec la semaine
        $stmt = $pdo->prepare("INSERT INTO seances 
                              (cours_id, salle_id, semaine_id, jour, heure_debut, heure_fin, enseignant_id) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $cours_id,
            $salle_id,
            $semaine_id,
            $jour,
            $heure_debut,
            $heure_fin,
            $enseignant_id
        ]);
        
        header("Location: salle_detail.php?id=$salle_id&semaine_id=$semaine_id&success=Séance ajoutée avec succès");
        exit;
    } catch (PDOException $e) {
        header("Location: salle_detail.php?id=$salle_id&semaine_id=$semaine_id&error=Erreur lors de l'ajout de la séance : " . $e->getMessage());
        exit;
    } catch (Exception $e) {
        header("Location: salle_detail.php?id=$salle_id&semaine_id=$semaine_id&error=" . $e->getMessage());
        exit;
    }
}

header("Location: index.php");
exit;