<?php
include 'includes/db.php';
require_once 'vendor/autoload.php';

use Dompdf\Dompdf;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $semaine_id = $_POST['semaine'] ?? '';
    $jour = $_POST['jour'] ?? '';
    $salle_id = $_POST['salle'] ?? '';
    $formation = $_POST['formation'] ?? 'FI';
    
    if (!empty($semaine_id) && !empty($jour) && !empty($salle_id)) {
        // 1. D'abord récupérer les séances correspondantes
        $querySeances = "SELECT se.id 
                        FROM seances se
                        JOIN salles sa ON se.salle_id = sa.id
                        WHERE se.semaine_id = ?
                        AND se.jour = ?
                        AND se.salle_id = ?
                        AND sa.formation = ?";
        
        $stmtSeances = $pdo->prepare($querySeances);
        $stmtSeances->execute([$semaine_id, $jour, $salle_id, $formation]);
        $seancesIds = $stmtSeances->fetchAll(PDO::FETCH_COLUMN);
        
        // 2. Ensuite récupérer les présences pour ces séances
        if (!empty($seancesIds)) {
            $placeholders = rtrim(str_repeat('?,', count($seancesIds)), ',');
            
            $queryPresences = "SELECT 
                                u.id AS etudiant_id,
                                u.name AS etudiant_nom,
                                u.phone AS etudiant_phone,
                                u.classroom AS salle_classe,
                                m.nom AS matiere_nom,
                                se.heure_debut,
                                se.heure_fin,
                                se.date_seance,
                                CONCAT(prof.name, ' (', TIME_FORMAT(se.debut_reel, '%H:%i'), '-', TIME_FORMAT(se.fin_reelle, '%H:%i'), ')') AS enseignant_info,
                                w.numero AS semaine_numero,
                                sa.nom AS salle_nom
                              FROM presences_etudiants pe
                              JOIN users u ON pe.etudiant_id = u.id
                              JOIN seances se ON pe.seance_id = se.id
                              JOIN salles sa ON se.salle_id = sa.id
                              JOIN semaines w ON se.semaine_id = w.id
                              JOIN cours c ON se.cours_id = c.id
                              JOIN matieres m ON c.matiere_id = m.id
                              JOIN users prof ON se.enseignant_id = prof.id
                              WHERE pe.seance_id IN ($placeholders)
                              AND pe.etat = 'present'
                              AND u.grade = 'Etudiant'
                              ORDER BY u.name, se.heure_debut";
            
            $stmtPresences = $pdo->prepare($queryPresences);
            $stmtPresences->execute($seancesIds);
            $presences = $stmtPresences->fetchAll();
            
            // Préparation des données pour le PDF (regroupement par étudiant)
            $presencesPdf = [];
            foreach ($presences as $presence) {
                $etudiant_id = $presence['etudiant_id'];
                if (!isset($presencesPdf[$etudiant_id])) {
                    $presencesPdf[$etudiant_id] = [
                        'etudiant_nom' => $presence['etudiant_nom'],
                        'etudiant_phone' => $presence['etudiant_phone'],
                        'salle_classe' => $presence['salle_classe'],
                        'salle_nom' => $presence['salle_nom'],
                        'semaine_numero' => $presence['semaine_numero']
                    ];
                }
            }
        } else {
            $presences = [];
            $presencesPdf = [];
        }
        
        // Génération du PDF
        if (isset($_POST['generate_pdf'])) {
            $html = generatePdfContent($presencesPdf, $jour, $formation, $semaine_id, $pdo);
            $dompdf = new Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            $salle_nom = $presences[0]['salle_nom'] ?? 'Salle';
            $semaine_numero = $presences[0]['semaine_numero'] ?? '0';
            $filename = "presences_{$salle_nom}_{$jour}_semaine{$semaine_numero}.pdf";
            
            $dompdf->stream($filename, ["Attachment" => true]);
            exit;
        }
    }
}

function generatePdfContent($presencesPdf, $jour, $formation, $semaine_id, $pdo) {
    // Récupérer les infos de la semaine
    $semaine_info = $pdo->prepare("SELECT numero, date_debut, date_fin FROM semaines WHERE id = ?");
    $semaine_info->execute([$semaine_id]);
    $semaine = $semaine_info->fetch();
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Liste de présence</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #6e48aa; text-align: center; }
            .header-info { margin-bottom: 20px; text-align: center; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #6e48aa; color: white; padding: 10px; text-align: left; }
            td { padding: 8px; border-bottom: 1px solid #ddd; }
            .footer { margin-top: 30px; text-align: right; font-size: 12px; }
        </style>
    </head>
    <body>
        <h1>Liste de présence</h1>
        <div class="header-info">
            <p><strong>Semaine:</strong> '.htmlspecialchars($semaine['numero']).' ('.htmlspecialchars($semaine['date_debut']).' au '.htmlspecialchars($semaine['date_fin']).')</p>
            <p><strong>Jour:</strong> '.htmlspecialchars($jour).'</p>
            <p><strong>Formation:</strong> '.htmlspecialchars($formation).'</p>';
    
    if (!empty($presencesPdf)) {
        $html .= '<p><strong>Salle:</strong> '.htmlspecialchars($presencesPdf[array_key_first($presencesPdf)]['salle_nom']).'</p>';
    }
    
    $html .= '</div>';
    
    if (!empty($presencesPdf)) {
        $html .= '<table>
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Étudiant</th>
                            <th>Téléphone</th>
                            <th>Salle de classe</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        $index = 1;
        foreach ($presencesPdf as $presence) {
            $html .= '<tr>
                        <td>'.$index.'</td>
                        <td>'.htmlspecialchars($presence['etudiant_nom']).'</td>
                        <td>'.htmlspecialchars($presence['etudiant_phone']).'</td>
                        <td>'.htmlspecialchars($presence['salle_classe']).'</td>
                      </tr>';
            $index++;
        }
        
        $html .= '</tbody></table>';
    } else {
        $html .= '<p>Aucune présence enregistrée pour les critères sélectionnés.</p>';
    }
    
    $html .= '<div class="footer">
                <p>Généré le '.date('d/m/Y à H:i').'</p>
              </div>
    </body>
    </html>';
    
    return $html;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Présences</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .btn-neon {
            background: linear-gradient(90deg, #6e48aa 0%, #9d50bb 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
            box-shadow: 0 0 15px rgba(110, 72, 170, 0.5);
        }
        .btn-neon:hover {
            transform: translateY(-3px);
            box-shadow: 0 0 20px rgba(110, 72, 170, 0.8);
            color: white;
        }
        .card-presence {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .table-presence th {
            background-color: #6e48aa;
            color: white;
        }
        .title {
            color: #6e48aa;
            text-shadow: 0 2px 4px rgba(110, 72, 170, 0.2);
        }
        .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        .form-select:focus {
            border-color: #6e48aa;
            box-shadow: 0 0 0 0.25rem rgba(110, 72, 170, 0.25);
        }
    </style>
</head>
<body>
<?php include 'includes/admin-header.php'; ?>

<div class="container py-5">
    <h1 class="text-center mb-5 title">Liste des Présences Étudiantes</h1>
    
    <div class="card card-presence">
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-3">
                    <label for="semaine" class="form-label">Semaine</label>
                    <select class="form-select" id="semaine" name="semaine" required>
                        <option value="">Sélectionnez une semaine</option>
                        <?php
                        $semaines = $pdo->query("SELECT * FROM semaines ORDER BY numero");
                        while ($semaine = $semaines->fetch()):
                        ?>
                        <option value="<?= $semaine['id'] ?>" <?= isset($semaine_id) && $semaine_id == $semaine['id'] ? 'selected' : '' ?>>
                            Semaine <?= $semaine['numero'] ?> (<?= $semaine['date_debut'] ?> au <?= $semaine['date_fin'] ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="jour" class="form-label">Jour</label>
                    <select class="form-select" id="jour" name="jour" required>
                        <option value="">Sélectionnez un jour</option>
                        <option value="LUNDI" <?= isset($jour) && $jour == 'LUNDI' ? 'selected' : '' ?>>Lundi</option>
                        <option value="MARDI" <?= isset($jour) && $jour == 'MARDI' ? 'selected' : '' ?>>Mardi</option>
                        <option value="MERCREDI" <?= isset($jour) && $jour == 'MERCREDI' ? 'selected' : '' ?>>Mercredi</option>
                        <option value="JEUDI" <?= isset($jour) && $jour == 'JEUDI' ? 'selected' : '' ?>>Jeudi</option>
                        <option value="VENDREDI" <?= isset($jour) && $jour == 'VENDREDI' ? 'selected' : '' ?>>Vendredi</option>
                        <option value="SAMEDI" <?= isset($jour) && $jour == 'SAMEDI' ? 'selected' : '' ?>>Samedi</option>
                        <option value="DIMANCHE" <?= isset($jour) && $jour == 'DIMANCHE' ? 'selected' : '' ?>>Dimanche</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="salle" class="form-label">Salle</label>
                    <select class="form-select" id="salle" name="salle" required>
                        <option value="">Sélectionnez une salle</option>
                        <?php
                        $salles = $pdo->query("SELECT * FROM salles ORDER BY nom");
                        while ($salle = $salles->fetch()):
                        ?>
                        <option value="<?= $salle['id'] ?>" <?= isset($salle_id) && $salle_id == $salle['id'] ? 'selected' : '' ?>>
                            <?= $salle['nom'] ?> (<?= $salle['formation'] ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="formation" class="form-label">Formation</label>
                    <select class="form-select" id="formation" name="formation">
                        <option value="FI" <?= isset($formation) && $formation == 'FI' ? 'selected' : '' ?>>Formation Initiale</option>
                        <option value="FA" <?= isset($formation) && $formation == 'FA' ? 'selected' : '' ?>>Formation Alternance</option>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-neon w-100">
                        <i class="fas fa-search me-2"></i> Rechercher
                    </button>
                </div>
                
                <?php if (!empty($presences)): ?>
                <div class="col-12 mt-3">
                    <button type="submit" name="generate_pdf" class="btn btn-neon">
                        <i class="fas fa-file-pdf me-2"></i> Générer PDF
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <?php if (!empty($presences)): ?>
    <div class="card card-presence">
        <div class="card-body">
            <h3 class="mb-4" style="color: #6e48aa;">Résultats</h3>
            
            <div class="table-responsive">
                <table class="table table-presence table-hover">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Étudiant</th>
                            <th>Téléphone</th>
                            <th>Salle de classe</th>
                            <th>Matière</th>
                            <th>Heure</th>
                            <th>Enseignant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($presences as $index => $presence): ?>
                        <tr>
                            <td><?= $index+1 ?></td>
                            <td><?= htmlspecialchars($presence['etudiant_nom']) ?></td>
                            <td><?= htmlspecialchars($presence['etudiant_phone']) ?></td>
                            <td><?= htmlspecialchars($presence['salle_classe']) ?></td>
                            <td><?= htmlspecialchars($presence['matiere_nom']) ?></td>
                            <td><?= htmlspecialchars($presence['heure_debut']) ?> - <?= htmlspecialchars($presence['heure_fin']) ?></td>
                            <td><?= htmlspecialchars($presence['enseignant_info']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php elseif (isset($presences)): ?>
    <div class="alert alert-info">
        Aucune présence enregistrée pour les critères sélectionnés.
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>