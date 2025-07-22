<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérification de l'accès délégué

// Vérification de l'ID de séance
if (!isset($_GET['seance_id'])) {
    $_SESSION['error'] = "Aucune séance spécifiée.";
    header('Location: dashboard.php');
    exit();
}

$seance_id = intval($_GET['seance_id']);

// Récupération des infos de la séance avec vérification d'accès
$query = "SELECT s.*, sl.nom as salle_nom, p.etudiants_presents, p.status as push_status,
                 m.nom as matiere_nom, m.code as matiere_code,
                 u.name as enseignant_nom
          FROM seances s
          JOIN salles sl ON s.salle_id = sl.id
          JOIN cours c ON s.cours_id = c.id
          JOIN matieres m ON c.matiere_id = m.id
          JOIN users u ON s.enseignant_id = u.id
          LEFT JOIN pushes p ON s.id = p.seance_id
          WHERE s.id = ? AND sl.nom = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$seance_id, $_SESSION['classroom']]);
$seance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seance) {
    $_SESSION['error'] = "Séance introuvable ou vous n'avez pas accès à cette séance.";
    header('Location: dashboard.php');
    exit();
}

// Vérification si la séance est verrouillée
$is_locked = ($seance['commentaires'] === 'PRESENCES_VERROUILLEES');

// Traitement du formulaire de confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {
    if (isset($_POST['confirm_presences'])) {
        // Récupérer tous les étudiants de la salle
        $query = "SELECT id FROM users WHERE grade = 'Etudiant' AND classroom = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$_SESSION['classroom']]);
        $all_students = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Récupérer les étudiants confirmés
        $confirmed_students = isset($_POST['students']) ? $_POST['students'] : [];
        $confirmed_count = count($confirmed_students);
        
        // Validation du nombre de présences
        if ($confirmed_count > $seance['etudiants_presents']) {
            $_SESSION['error'] = "Le nombre de confirmations ($confirmed_count) dépasse le nombre d'étudiants présents déclaré ({$seance['etudiants_presents']}).";
            header("Location: liste.php?seance_id=$seance_id");
            exit();
        }
        
        // Début de la transaction
        $pdo->beginTransaction();
        
        try {
            // Marquer tous les étudiants comme absents d'abord
            foreach ($all_students as $student_id) {
                $query = "INSERT INTO presences_etudiants (seance_id, etudiant_id, etat, date_marquage) 
                          VALUES (?, ?, 'absent', NOW())
                          ON DUPLICATE KEY UPDATE etat = 'absent', date_marquage = NOW()";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$seance_id, $student_id]);
            }
            
            // Ensuite marquer les présents
            foreach ($confirmed_students as $student_id) {
                $query = "UPDATE presences_etudiants SET etat = 'present', date_marquage = NOW() 
                          WHERE seance_id = ? AND etudiant_id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$seance_id, $student_id]);
            }
            
            // Verrouiller la séance
            $query = "UPDATE seances SET commentaires = 'PRESENCES_VERROUILLEES' WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$seance_id]);
            
            // Mettre à jour le statut du push
            if ($seance['push_status'] === 'pending') {
                $query = "UPDATE pushes SET status = 'approved' WHERE seance_id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$seance_id]);
            }
            
            $pdo->commit();
            
            // Générer le PDF
            generatePresencePDF($seance_id, $seance, $confirmed_students, $_SESSION['classroom']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Une erreur est survenue lors de l'enregistrement des présences.";
            header("Location: liste.php?seance_id=$seance_id");
            exit();
        }
    }
}

function generatePresencePDF($seance_id, $seance, $confirmed_students, $classroom) {
    require_once 'includes/tcpdf/tcpdf.php';
    
    // Création du PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configuration
    $pdf->SetCreator('Système de Gestion des Présences');
    $pdf->SetAuthor($_SESSION['name']);
    $pdf->SetTitle("Liste de présence - Séance $seance_id");
    $pdf->SetSubject('Liste de présence');
    $pdf->AddPage();
    
    // Contenu HTML
    $html = '<h1 style="text-align:center;">Liste de Présence</h1>';
    $html .= '<h2 style="text-align:center;">Séance du ' . date('d/m/Y', strtotime($seance['date_seance'] ?? 'now')) . '</h2>';
    $html .= '<p><strong>Matière:</strong> ' . htmlspecialchars($seance['matiere_nom']) . ' (' . htmlspecialchars($seance['matiere_code']) . ')</p>';
    $html .= '<p><strong>Salle:</strong> ' . htmlspecialchars($seance['salle_nom']) . ' (' . htmlspecialchars($seance['formation'] ?? 'FI') . ')</p>';
    $html .= '<p><strong>Heure:</strong> ' . htmlspecialchars($seance['heure_debut']) . ' - ' . htmlspecialchars($seance['heure_fin']) . '</p>';
    
    // Récupération des étudiants avec leur statut
    $query = "SELECT u.id, u.name, u.formation, f.nom as filiere, n.nom as niveau, 
                     pe.etat as presence_etat
              FROM users u
              LEFT JOIN filieres f ON u.filiere_id = f.id
              LEFT JOIN niveaux n ON u.niveau_id = n.id
              LEFT JOIN presences_etudiants pe ON pe.seance_id = ? AND pe.etudiant_id = u.id
              WHERE u.grade = 'Etudiant' AND u.classroom = ?
              ORDER BY u.name";
    $stmt = $GLOBALS['pdo']->prepare($query);
    $stmt->execute([$seance_id, $classroom]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcul des statistiques
    $stats = [
        'FA_present' => 0,
        'FI_present' => 0,
        'absent' => 0,
        'total' => count($students)
    ];
    
    foreach ($students as $student) {
        if ($student['presence_etat'] === 'present') {
            if ($student['formation'] === 'FA') {
                $stats['FA_present']++;
            } else {
                $stats['FI_present']++;
            }
        } else {
            $stats['absent']++;
        }
    }
    
    // Affichage des statistiques
    $html .= '<div style="margin: 10px 0; padding: 10px; border: 1px solid #ddd; background-color: #f9f9f9;">
                <h3 style="margin-top:0;">Statistiques de présence</h3>
                <table style="width:100%;">
                    <tr>
                        <td style="width:33%;"><strong>FA Présents:</strong> ' . $stats['FA_present'] . '</td>
                        <td style="width:33%;"><strong>FI Présents:</strong> ' . $stats['FI_present'] . '</td>
                        <td style="width:33%;"><strong>Absents:</strong> ' . $stats['absent'] . '</td>
                    </tr>
                    <tr>
                        <td colspan="3" style="padding-top:5px;"><strong>Total étudiants:</strong> ' . $stats['total'] . '</td>
                    </tr>
                </table>
              </div>';
    
    $html .= '<h3>Détail des étudiants (' . $stats['total'] . ')</h3>';
    $html .= '<table border="1" cellpadding="5" style="width:100%">
                <tr>
                    <th width="25%">Nom</th>
                    <th width="15%">Formation</th>
                    <th width="20%">Filière</th>
                    <th width="15%">Niveau</th>
                    <th width="15%">Présence</th>
                </tr>';
    
    $salle_formation = $seance['formation'] ?? 'FI'; // Par défaut FI si non défini
    
    foreach ($students as $student) {
        $presence = ($student['presence_etat'] === 'present') ? 'Présent' : 'Absent';
        $presence_color = ($student['presence_etat'] === 'present') ? '#4CAF50' : '#F44336';
        
        // Vérifier si l'étudiant est dans la mauvaise formation pour cette salle
        $is_wrong_formation = ($salle_formation === 'FI' && $student['formation'] === 'FA') || 
                             ($salle_formation === 'FA' && $student['formation'] === 'FI');
        
        // Style pour les étudiants en mauvaise formation (jaune avec texte en noir pour lisibilité)
        $row_style = $is_wrong_formation ? 'background-color:#FFC107;color:#000000;' : '';
        
        $html .= '<tr style="'.$row_style.'">
                    <td>' . htmlspecialchars($student['name']) . '</td>
                    <td>' . htmlspecialchars($student['formation']) . '</td>
                    <td>' . htmlspecialchars($student['filiere']) . '</td>
                    <td>' . htmlspecialchars($student['niveau']) . '</td>
                    <td style="color:' . $presence_color . ';font-weight:bold;">' . $presence . '</td>
                </tr>';
    }
    
    $html .= '</table>';
    
    // Légende pour expliquer la couleur jaune
    $html .= '<div style="margin-top: 10px; padding: 5px; background-color: #FFC107; color: #000000; display: inline-block;">
                <i class="fas fa-info-circle"></i> Étudiant en formation ' . ($salle_formation === 'FI' ? 'FA' : 'FI') . ' dans une salle ' . $salle_formation . '
              </div>';
    
    // Pied de page
    $html .= '<p style="margin-top:20px;text-align:right;">Généré le ' . date('d/m/Y H:i') . ' par ' . htmlspecialchars($_SESSION['name']) . '</p>';
    
    // Ajout du contenu
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Génération du fichier
    $filename = 'presence_seance_' . $seance_id . '_' . date('Ymd_His') . '.pdf';
    $pdf->Output($filename, 'D'); // Téléchargement direct
    exit();
}

// Récupération des étudiants pour l'affichage
$query = "SELECT u.id, u.name, u.formation, f.nom as filiere, n.nom as niveau, 
                 pe.etat as presence_etat
          FROM users u
          LEFT JOIN filieres f ON u.filiere_id = f.id
          LEFT JOIN niveaux n ON u.niveau_id = n.id
          LEFT JOIN presences_etudiants pe ON pe.seance_id = ? AND pe.etudiant_id = u.id
          WHERE u.grade = 'Etudiant' AND u.classroom = ?
          ORDER BY u.name";
$stmt = $pdo->prepare($query);
$stmt->execute([$seance_id, $_SESSION['classroom']]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcul du nombre de confirmations
$confirmed_count = 0;
foreach ($students as $student) {
    if ($student['presence_etat'] === 'present') {
        $confirmed_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation des Présences</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --violet-dark: #2a0a42;
            --violet-medium: #4b2a70;
            --violet-light: #7b4b9e;
            --violet-neon: #b388ff;
            --white: #ffffff;
            --gray-light: #f0f0f0;
            --black: #0a0a0a;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, var(--violet-dark), var(--black));
            color: var(--white);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(10, 10, 10, 0.7);
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            border: 1px solid var(--violet-medium);
        }
        
        h1 {
            color: var(--violet-neon);
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .seance-info {
            background: rgba(74, 20, 140, 0.3);
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 2rem;
            border-left: 3px solid var(--violet-neon);
        }
        
        .seance-info p {
            margin-bottom: 0.5rem;
        }
        
        .seance-info strong {
            color: var(--violet-neon);
        }
        
        .counter {
            background: var(--violet-medium);
            padding: 0.5rem 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            text-align: center;
            font-weight: bold;
        }
        
        .counter span {
            color: var(--violet-neon);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--violet-medium);
        }
        
        th {
            background-color: var(--violet-dark);
            color: var(--violet-neon);
            font-weight: 600;
        }
        
        tr:hover {
            background-color: rgba(123, 75, 158, 0.1);
        }
        
        .checkbox-cell {
            text-align: center;
            width: 50px;
        }
        
        input[type="checkbox"] {
            transform: scale(1.5);
        }
        
        .form-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background-color: var(--violet-neon);
            color: white;
        }
        
        .btn-secondary {
            background-color: #666;
            color: #ccc;
        }
        
        .btn-primary:hover {
            background-color: var(--violet-light);
            transform: translateY(-2px);
        }
        
        .presence-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .present {
            background-color: #4caf50;
            color: white;
        }
        
        .absent {
            background-color: #f44336;
            color: white;
        }
        
        .message {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            text-align: center;
        }
        
        .success {
            background-color: rgba(76, 175, 80, 0.2);
            border: 1px solid #4caf50;
            color: #a5d6a7;
        }
        
        .error {
            background-color: rgba(244, 67, 54, 0.2);
            border: 1px solid #f44336;
            color: #ef9a9a;
        }
        
        .warning {
            background-color: rgba(255, 193, 7, 0.2);
            border: 1px solid #ffc107;
            color: #ffecb3;
        }
        
        .disabled-checkbox {
            opacity: 0.6;
            pointer-events: none;
        }
        
        .locked-info {
            background-color: rgba(244, 67, 54, 0.2);
            border-left: 3px solid #f44336;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            th, td {
                padding: 0.75rem;
                font-size: 0.9rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-users"></i> Confirmation des Présences</h1>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message success">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($is_locked): ?>
            <div class="locked-info">
                <i class="fas fa-lock"></i>
                <div>
                    <strong>Les présences pour cette séance ont été verrouillées.</strong>
                    <p>Aucune modification n'est plus possible.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="seance-info">
            <p><strong>Matière:</strong> <?php echo htmlspecialchars($seance['matiere_nom']) . ' (' . htmlspecialchars($seance['matiere_code']) . ')'; ?></p>
            <p><strong>Enseignant:</strong> <?php echo htmlspecialchars($seance['enseignant_nom']); ?></p>
            <p><strong>Salle:</strong> <?php echo htmlspecialchars($seance['salle_nom']); ?></p>
            <p><strong>Heure:</strong> <?php echo htmlspecialchars($seance['heure_debut']) . ' - ' . htmlspecialchars($seance['heure_fin']); ?></p>
            <p><strong>Étudiants présents déclarés:</strong> <?php echo htmlspecialchars($seance['etudiants_presents']); ?></p>
            <?php if ($is_locked): ?>
                <p><strong>Statut:</strong> <span style="color: #f44336;">Verrouillé</span></p>
            <?php endif; ?>
        </div>
        
        <div class="counter">
            Confirmations: <span id="confirmedCount"><?php echo $confirmed_count; ?></span> / <?php echo htmlspecialchars($seance['etudiants_presents']); ?>
            <?php if ($confirmed_count > $seance['etudiants_presents']): ?>
                <span style="color: #f44336; margin-left: 1rem;">
                    <i class="fas fa-exclamation-triangle"></i> Nombre supérieur au déclaré
                </span>
            <?php endif; ?>
        </div>
        
        <form method="POST" id="presenceForm">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Formation</th>
                        <th>Filière</th>
                        <th>Niveau</th>
                        <th class="checkbox-cell">Présent</th>
                        <th>État</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                        <td><?php echo htmlspecialchars($student['formation']); ?></td>
                        <td><?php echo htmlspecialchars($student['filiere']); ?></td>
                        <td><?php echo htmlspecialchars($student['niveau']); ?></td>
                        <td class="checkbox-cell">
                            <input type="checkbox" name="students[]" value="<?php echo $student['id']; ?>" 
                                <?php echo $student['presence_etat'] === 'present' ? 'checked' : ''; ?>
                                <?php echo $is_locked ? 'disabled class="disabled-checkbox"' : 'onchange="updateCounter()"'; ?>>
                        </td>
                        <td>
                            <?php if ($student['presence_etat'] === 'present'): ?>
                                <span class="presence-badge present"><i class="fas fa-check"></i> Présent</span>
                            <?php else: ?>
                                <span class="presence-badge absent"><i class="fas fa-times"></i> Absent</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="form-actions">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
                <?php if (!$is_locked): ?>
                <button type="submit" name="confirm_presences" class="btn btn-primary" id="confirmBtn">
                    <i class="fas fa-file-pdf"></i> Confirmer et Générer PDF
                </button>
                <?php else: ?>
                <a href="javascript:window.print()" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Imprimer
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <script>
        function updateCounter() {
            const checkboxes = document.querySelectorAll('input[name="students[]"]:checked');
            const confirmedCount = document.getElementById('confirmedCount');
            const confirmBtn = document.getElementById('confirmBtn');
            const expectedCount = <?php echo $seance['etudiants_presents']; ?>;
            
            confirmedCount.textContent = checkboxes.length;
            
            // Avertissement si nombre de confirmations > nombre déclaré
            if (checkboxes.length > expectedCount) {
                confirmedCount.style.color = '#f44336';
                confirmBtn.disabled = true;
                confirmBtn.title = 'Le nombre de confirmations dépasse le nombre déclaré';
            } else {
                confirmedCount.style.color = '';
                confirmBtn.disabled = false;
                confirmBtn.title = '';
            }
        }
        
        // Initialiser le compteur
        document.addEventListener('DOMContentLoaded', updateCounter);
    </script>
</body>
</html>