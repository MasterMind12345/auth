<?php
include 'includes/db.php';

$salle_id = $_GET['salle_id'] ?? 0;
$semaine_id = $_GET['semaine_id'] ?? 1;

// Récupérer les informations de la salle
$stmt = $pdo->prepare("SELECT s.*, f.nom as filiere_nom, n.nom as niveau_nom,
                      d.name as delegue_nom, d.phone as delegue_phone
                      FROM salles s
                      JOIN filieres f ON s.filiere_id = f.id
                      JOIN niveaux n ON f.niveau_id = n.id
                      LEFT JOIN users d ON d.classroom = s.nom AND d.grade = 'Delegue' AND d.formation = s.formation
                      WHERE s.id = ?");
$stmt->execute([$salle_id]);
$salle = $stmt->fetch();

// Récupérer les informations de la semaine
$stmt = $pdo->prepare("SELECT * FROM semaines WHERE id = ?");
$stmt->execute([$semaine_id]);
$semaine = $stmt->fetch();

// Récupérer les séances effectuées pour cette salle et cette semaine
$stmt = $pdo->prepare("SELECT 
                      sc.id as seance_id,
                      sc.jour as jour_effectif,
                      sc.heure_debut as heure_debut_effective,
                      sc.heure_fin as heure_fin_effective,
                      sc.debut_reel,
                      sc.fin_reelle,
                      sc.etat_delegue,
                      sc.etat_prof,
                      sc.etat_final,
                      c.id as cours_id,
                      c.jour as jour_programme,
                      c.heure_debut as heure_debut_programmee,
                      c.heure_fin as heure_fin_programmee,
                      m.nom as matiere_nom, 
                      m.code as matiere_code,
                      u.name as enseignant_nom
                      FROM seances sc
                      JOIN cours c ON sc.cours_id = c.id
                      JOIN matieres m ON c.matiere_id = m.id
                      JOIN users u ON sc.enseignant_id = u.id
                      WHERE sc.salle_id = ?
                      ORDER BY sc.jour, sc.heure_debut");
$stmt->execute([$salle_id]);
$seances = $stmt->fetchAll();

// Récupérer également les cours programmés pour cette salle et semaine
$stmt = $pdo->prepare("SELECT 
                      c.id as cours_id,
                      c.jour as jour_programme,
                      c.heure_debut as heure_debut_programmee,
                      c.heure_fin as heure_fin_programmee,
                      m.nom as matiere_nom, 
                      m.code as matiere_code,
                      u.name as enseignant_nom
                      FROM emplois_temps et
                      JOIN cours c ON c.emploi_id = et.id
                      JOIN matieres m ON c.matiere_id = m.id
                      JOIN users u ON c.enseignant_id = u.id
                      WHERE et.salle_id = ? AND et.semaine_id = ?
                      ORDER BY c.jour, c.heure_debut");
$stmt->execute([$salle_id, $semaine_id]);
$cours_programmes = $stmt->fetchAll();

// Fusionner les données des séances effectuées et des cours programmés
$toutes_seances = [];

// D'abord ajouter les séances effectuées
foreach ($seances as $seance) {
    $toutes_seances[] = [
        'type' => 'effectuee',
        'data' => $seance
    ];
}

// Ensuite ajouter les cours programmés qui n'ont pas de séance effectuée
foreach ($cours_programmes as $cours) {
    $trouve = false;
    foreach ($seances as $seance) {
        if ($seance['cours_id'] == $cours['cours_id']) {
            $trouve = true;
            break;
        }
    }
    if (!$trouve) {
        $toutes_seances[] = [
            'type' => 'programme',
            'data' => $cours
        ];
    }
}

// Organiser les séances par jour
$jours_ordre = ['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI', 'SAMEDI', 'DIMANCHE'];
$seances_par_jour = array_fill_keys($jours_ordre, []);

foreach ($toutes_seances as $item) {
    $seance = $item['data'];
    $jour = $seance['jour_effectif'] ?? $seance['jour_programme'];
    
    $seance_formatee = [
        'matiere_code' => $seance['matiere_code'],
        'matiere_nom' => $seance['matiere_nom'],
        'enseignant_nom' => $seance['enseignant_nom'],
        'heure_debut' => $item['type'] === 'effectuee' ? $seance['heure_debut_effective'] : $seance['heure_debut_programmee'],
        'heure_fin' => $item['type'] === 'effectuee' ? $seance['heure_fin_effective'] : $seance['heure_fin_programmee'],
        'etat_delegue' => $seance['etat_delegue'] ?? null,
        'etat_prof' => $seance['etat_prof'] ?? null,
        'etat_final' => $seance['etat_final'] ?? null,
        'effectuee' => $item['type'] === 'effectuee',
        'conflit' => false
    ];
    
    // Calculer la durée
    $debut = new DateTime($seance_formatee['heure_debut']);
    $fin = new DateTime($seance_formatee['heure_fin']);
    $interval = $debut->diff($fin);
    $seance_formatee['duree'] = $interval->format('%Hh%I');
    
    $seances_par_jour[$jour][] = $seance_formatee;
}

// Calculer les statistiques
$stats = [
    'total' => count($cours_programmes),
    'effectuees' => count($seances),
    'annulees' => 0,
    'conflits' => 0
];

foreach ($seances as $seance) {
    if ($seance['etat_final'] === 'absent') {
        $stats['annulees']++;
    }
}

// Le reste du code pour générer le PDF reste inchangé...
// [Insérer ici le reste du code HTML/PDF comme dans votre version originale]

// Générer le PDF
ob_start();
?>



<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport des séances - <?= htmlspecialchars($salle['nom']) ?> - Semaine <?= $semaine['numero'] ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .programme {
            background-color: #f0f8ff;
        }
        .effectuee {
            background-color: #f0fff0;
        }
        .absent {
            color: #ff0000;
            font-weight: bold;
        }
        .present {
            color: #008000;
            font-weight: bold;
        }
        .conflit {
            background-color: #fff0f0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Université de IUT</h2>
        <h3>Rapport des séances - Semaine <?= $semaine['numero'] ?> (<?= $semaine['date_debut'] ?> au <?= $semaine['date_fin'] ?>)</h3>
        <h4>Salle: <?= htmlspecialchars($salle['nom']) ?> | Filière: <?= htmlspecialchars($salle['filiere_nom']) ?> | Niveau: <?= htmlspecialchars($salle['niveau_nom']) ?></h4>
        <?php if ($salle['delegue_nom']): ?>
            <p>Délégué: <?= htmlspecialchars($salle['delegue_nom']) ?> (<?= htmlspecialchars($salle['delegue_phone']) ?>)</p>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">Jour</th>
                <th rowspan="2">Codes et intitulés des EC</th>
                <th rowspan="2">Noms des enseignants</th>
                <th colspan="2">Heures</th>
                <th rowspan="2">Durée</th>
                <th rowspan="2">Signature enseignant</th>
                <th rowspan="2">Signature délégué</th>
                <th rowspan="2">Présence finale</th>
            </tr>
            <tr>
                <th>Début</th>
                <th>Fin</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $current_day = null;
            foreach ($jours_ordre as $jour): 
                if (!empty($seances_par_jour[$jour])): 
                    $first_row = true;
                    foreach ($seances_par_jour[$jour] as $seance): ?>
                        <tr class="<?= $seance['effectuee'] ? 'effectuee' : 'programme' ?><?= $seance['conflit'] ? ' conflit' : '' ?>">
                            <?php if ($first_row): ?>
                                <td rowspan="<?= count($seances_par_jour[$jour]) ?>"><?= ucfirst(strtolower($jour)) ?></td>
                                <?php $first_row = false; ?>
                            <?php endif; ?>
                            <td><?= htmlspecialchars($seance['matiere_code']) ?> - <?= htmlspecialchars($seance['matiere_nom']) ?></td>
                            <td><?= htmlspecialchars($seance['enseignant_nom']) ?></td>
                            <td><?= substr($seance['heure_debut'], 0, 5) ?></td>
                            <td><?= substr($seance['heure_fin'], 0, 5) ?></td>
                            <td><?= $seance['duree'] ?></td>
                            <td>
                                <?php if ($seance['etat_prof'] !== null): ?>
                                    <span class="<?= $seance['etat_prof'] === 'present' ? 'present' : 'absent' ?>">
                                        <?= $seance['etat_prof'] === 'present' ? '✓ Présent' : '✗ Absent' ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($seance['etat_delegue'] !== null): ?>
                                    <span class="<?= $seance['etat_delegue'] === 'present' ? 'present' : 'absent' ?>">
                                        <?= $seance['etat_delegue'] === 'present' ? '✓ Présent' : '✗ Absent' ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($seance['etat_final'] !== null): ?>
                                    <span class="<?= $seance['etat_final'] === 'present' ? 'present' : 'absent' ?>">
                                        <?= $seance['etat_final'] === 'present' ? '✓ Effectuée' : '✗ Non effectuée' ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td><?= ucfirst(strtolower($jour)) ?></td>
                        <td colspan="8">Aucune séance programmée</td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html_format1 = ob_get_clean();

// Deuxième format (statistiques)
ob_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Statistiques - <?= htmlspecialchars($salle['nom']) ?> - Semaine <?= $semaine['numero'] ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .stats-container {
            display: flex;
            justify-content: space-around;
            margin-bottom: 30px;
        }
        .stat-box {
            border: 1px solid #000;
            padding: 15px;
            text-align: center;
            min-width: 150px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }
        .conflits-list {
            margin-top: 30px;
            padding: 15px;
            border: 1px solid #ddd;
        }
        .footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        .signature {
            height: 50px;
            border-top: 1px solid #000;
            margin-top: 30px;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Université de IUT</h2>
        <h3>Statistiques des séances - Semaine <?= $semaine['numero'] ?> (<?= $semaine['date_debut'] ?> au <?= $semaine['date_fin'] ?>)</h3>
        <h4>Salle: <?= htmlspecialchars($salle['nom']) ?> | Filière: <?= htmlspecialchars($salle['filiere_nom']) ?> | Niveau: <?= htmlspecialchars($salle['niveau_nom']) ?></h4>
    </div>

    <div class="stats-container">
        <div class="stat-box">
            <h4>Séances programmées</h4>
            <div class="stat-value"><?= $stats['total'] ?></div>
        </div>
        <div class="stat-box">
            <h4>Séances effectuées</h4>
            <div class="stat-value"><?= $stats['effectuees'] ?></div>
        </div>
        <div class="stat-box">
            <h4>Séances annulées</h4>
            <div class="stat-value"><?= $stats['annulees'] ?></div>
        </div>
        <div class="stat-box">
            <h4>Taux de réalisation</h4>
            <div class="stat-value"><?= $stats['total'] > 0 ? round(($stats['effectuees']/$stats['total'])*100) : 0 ?>%</div>
        </div>
    </div>

    <?php if (!empty($conflits)): ?>
        <div class="conflits-list">
            <h4>Conflits d'horaire détectés :</h4>
            <ul>
                <?php foreach ($conflits as $conflit): ?>
                    <li>
                        <?= $conflit['seance1']['matiere_nom'] ?> (<?= substr($conflit['seance1']['heure_debut'], 0, 5) ?>-<?= substr($conflit['seance1']['heure_fin'], 0, 5) ?>) 
                        avec 
                        <?= $conflit['seance2']['matiere_nom'] ?> (<?= substr($conflit['seance2']['heure_debut'], 0, 5) ?>-<?= substr($conflit['seance2']['heure_fin'], 0, 5) ?>)
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="footer">
        <div>
            <p>Le Responsable de la filière</p>
            <div class="signature"></div>
        </div>
        <div>
            <p>Fait à [Ville], le <?= date('d/m/Y') ?></p>
            <p>Signature délégué:</p>
            <div class="signature"></div>
        </div>
    </div>
</body>
</html>
<?php
$html_format2 = ob_get_clean();

// Générer les PDF
require_once 'vendor/autoload.php';
use Dompdf\Dompdf;

// Premier PDF (tableau des séances)
$dompdf1 = new Dompdf();
$dompdf1->loadHtml($html_format1);
$dompdf1->setPaper('A4', 'landscape');
$dompdf1->render();

// Deuxième PDF (statistiques)
$dompdf2 = new Dompdf();
$dompdf2->loadHtml($html_format2);
$dompdf2->setPaper('A4', 'portrait');
$dompdf2->render();

// Output the generated PDFs
$dompdf1->stream("tableau_seances_{$salle['nom']}_semaine_{$semaine['numero']}.pdf", ["Attachment" => false]);
$dompdf2->stream("statistiques_seances_{$salle['nom']}_semaine_{$semaine['numero']}.pdf", ["Attachment" => false]);