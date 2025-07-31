<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Vérification de la connexion
if (!isStudent()) {
    header('Location: index.php');
    exit();
}

// Configuration du fuseau horaire
date_default_timezone_set('Africa/Douala');

// Initialisation des variables
$seances = [];
$current_time = date('H:i:s');
$current_date = date('Y-m-d');
$current_day = strtoupper(date('l'));
$today_french = '';

// Conversion du jour en français
switch($current_day) {
    case 'MONDAY': $today_french = 'LUNDI'; break;
    case 'TUESDAY': $today_french = 'MARDI'; break;
    case 'WEDNESDAY': $today_french = 'MERCREDI'; break;
    case 'THURSDAY': $today_french = 'JEUDI'; break;
    case 'FRIDAY': $today_french = 'VENDREDI'; break;
    case 'SATURDAY': $today_french = 'SAMEDI'; break;
    case 'SUNDAY': $today_french = 'DIMANCHE'; break;
    default: $today_french = '';
}

// Récupération de la semaine actuelle
$current_week = getCurrentWeek($pdo);

// Récupération des séances pour l'étudiant
if (!isset($_SESSION['classroom'])) {
    $_SESSION['error'] = "Votre salle de classe n'est pas définie dans votre profil.";
} else {
    $query = "SELECT s.*, c.matiere_id, m.nom as matiere_nom, m.code as matiere_code, 
                     u.name as enseignant_nom, u.phone as enseignant_phone,
                     sl.nom as salle_nom, f.nom as filiere_nom,
                     emp.semaine_id, sem.numero as semaine_num,
                     pe.etat as etat_etudiant, s.commentaires
              FROM seances s
              JOIN cours c ON s.cours_id = c.id
              JOIN matieres m ON c.matiere_id = m.id
              JOIN users u ON s.enseignant_id = u.id
              JOIN salles sl ON s.salle_id = sl.id
              JOIN filieres f ON sl.filiere_id = f.id
              JOIN emplois_temps emp ON c.emploi_id = emp.id
              JOIN semaines sem ON emp.semaine_id = sem.id
              LEFT JOIN presences_etudiants pe ON pe.seance_id = s.id AND pe.etudiant_id = ?
              WHERE sl.nom = ?
              AND s.jour = ?
              AND emp.semaine_id = ?
              ORDER BY s.heure_debut";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $_SESSION['classroom'], $today_french, $current_week['id']]);
    $seances = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($seances)) {
        $_SESSION['salle_nom'] = $seances[0]['salle_nom'];
        $_SESSION['filiere'] = $seances[0]['filiere_nom'];
    }
}

// Traitement du formulaire de présence étudiant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seance_id'])) {
    $seance_id = $_POST['seance_id'];
    $status = $_POST['status'];
    
    // Vérifier que la séance n'est pas verrouillée
    $query = "SELECT commentaires FROM seances WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$seance_id]);
    $seance = $stmt->fetch();
    
    if ($seance['commentaires'] === 'PRESENCES_VERROUILLEES') {
        $_SESSION['error'] = "Les présences pour cette séance ont été verrouillées. Vous ne pouvez plus marquer votre présence.";
        header("Location: dashEtudiant.php");
        exit();
    }
    
    // Vérifier que la séance appartient bien à la salle de l'étudiant
    $query = "SELECT s.id 
              FROM seances s
              JOIN salles sl ON s.salle_id = sl.id
              WHERE s.id = ? AND sl.nom = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$seance_id, $_SESSION['classroom']]);
    
    if ($stmt->fetch() !== false) {
        // Vérifier si l'étudiant a déjà marqué sa présence
        $query = "SELECT id FROM presences_etudiants WHERE seance_id = ? AND etudiant_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$seance_id, $_SESSION['user_id']]);
        
        if ($stmt->fetch()) {
            // Mise à jour de la présence existante
            $query = "UPDATE presences_etudiants SET etat = ? WHERE seance_id = ? AND etudiant_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$status, $seance_id, $_SESSION['user_id']]);
        } else {
            // Insertion d'une nouvelle présence
            $query = "INSERT INTO presences_etudiants (seance_id, etudiant_id, etat) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$seance_id, $_SESSION['user_id'], $status]);
        }
        
        $_SESSION['message'] = "Votre présence a été enregistrée avec succès!";
        header("Location: dashEtudiant.php");
        exit();
    } else {
        $_SESSION['error'] = "Vous ne pouvez pas marquer la présence pour cette séance.";
    }
}

function getCurrentWeek($pdo) {
    $current_date = date('Y-m-d');
    $query = "SELECT * FROM semaines WHERE date_debut <= ? AND date_fin >= ? LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$current_date, $current_date]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$week) {
        $query = "SELECT * FROM semaines 
                  ORDER BY ABS(DATEDIFF(date_debut, ?)) 
                  LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$current_date]);
        $week = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return $week ?: ['id' => 0, 'numero' => 0, 'date_debut' => $current_date, 'date_fin' => $current_date];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Étudiant</title>
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
        }
        
        .navbar {
            background: linear-gradient(to right, var(--violet-dark), var(--violet-medium));
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar a {
            color: var(--white);
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: all 0.3s ease;
            background-color: var(--violet-light);
        }
        
        .navbar a:hover {
            background-color: var(--violet-neon);
            transform: translateY(-2px);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: rgba(123, 75, 158, 0.2);
            border-radius: 8px;
            border-left: 4px solid var(--violet-neon);
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, var(--violet-neon), var(--white));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .card {
            background: rgba(10, 10, 10, 0.7);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            border: 1px solid var(--violet-medium);
        }
        
        .card h2 {
            color: var(--violet-neon);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--violet-medium);
            font-size: 1.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .info-item {
            background: rgba(74, 20, 140, 0.3);
            padding: 1rem;
            border-radius: 6px;
            border-left: 3px solid var(--violet-neon);
        }
        
        .info-item strong {
            color: var(--violet-neon);
            display: block;
            margin-bottom: 0.3rem;
        }
        
        .seance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: rgba(10, 10, 10, 0.7);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .seance-table th, 
        .seance-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--violet-medium);
        }
        
        .seance-table th {
            background-color: var(--violet-dark);
            color: var(--violet-neon);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 1px;
        }
        
        .seance-table tr:hover {
            background-color: rgba(123, 75, 158, 0.1);
        }
        
        .status-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .present {
            background-color: #4caf50;
            color: white;
        }
        
        .absent {
            background-color: #f44336;
            color: white;
        }
        
        .disabled {
            background-color: #666;
            color: #ccc;
            cursor: not-allowed;
        }
        
        .time-display {
            padding: 0.5rem;
            background-color: #333;
            border-radius: 4px;
            color: #ccc;
        }
        
        .message {
            padding: 1rem;
            margin: 1rem 0;
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
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .badge-primary {
            background-color: var(--violet-medium);
            color: white;
        }
        
        .badge-secondary {
            background-color: var(--violet-light);
            color: white;
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                padding: 1rem;
            }
            
            .navbar div {
                margin-bottom: 1rem;
            }
            
            .seance-table {
                display: block;
                overflow-x: auto;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .seance-table th, 
            .seance-table td {
                padding: 0.75rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div>
            <i class="fas fa-user-circle"></i> Bienvenue, <?php echo htmlspecialchars($_SESSION['name']); ?> 
            <span style="color: var(--violet-neon);">(<?php echo htmlspecialchars($_SESSION['grade']); ?>)</span>
        </div>
        <div>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
    </div>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-tachometer-alt"></i> Tableau de Bord Étudiant</h1>
            <p><?php echo date('l d F Y'); ?></p>
            <p>Semaine <?php echo $current_week['numero']; ?> (<?php echo date('d/m/Y', strtotime($current_week['date_debut'])); ?> - <?php echo date('d/m/Y', strtotime($current_week['date_fin'])); ?>)</p>
        </div>
        
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
        
        <div class="card">
            <h2><i class="fas fa-info-circle"></i> Informations du compte</h2>
            <div class="info-grid">
                <div class="info-item">
                    <strong>Nom complet</strong>
                    <?php echo htmlspecialchars($_SESSION['name']); ?>
                </div>
                <div class="info-item">
                    <strong>Grade</strong>
                    <?php echo htmlspecialchars($_SESSION['grade']); ?>
                </div>
                <div class="info-item">
                    <strong>Salle/Classe</strong>
                    <span class="badge badge-primary"><?php echo htmlspecialchars($_SESSION['classroom'] ?? 'Non spécifié'); ?></span>
                </div>
                <div class="info-item">
                    <strong>Filière</strong>
                    <span class="badge badge-secondary"><?php echo htmlspecialchars($_SESSION['filiere'] ?? 'Non spécifié'); ?></span>
                </div>
            </div>
        </div>
        
        <?php if (!empty($seances)): ?>
        <div class="card">
            <h2><i class="fas fa-calendar-alt"></i> Séances du jour (<?php echo $today_french; ?>)</h2>
            <table class="seance-table">
                <thead>
                    <tr>
                        <th>Matière</th>
                        <th>Enseignant</th>
                        <th>Salle</th>
                        <th>Heure début</th>
                        <th>Heure fin</th>
                        <th>Début réel</th>
                        <th>Fin réelle</th>
                        <th>État Délégué</th>
                        <th>État Professeur</th>
                        <th>Votre présence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($seances as $seance): 
                        $current_time_display = date('H:i:s');
                        $start_time = new DateTime($seance['heure_debut']);
                        $end_time = new DateTime($seance['heure_fin']);
                        $now = new DateTime($current_time_display);
                        
                        $margin = new DateInterval('PT15M');
                        $start_with_margin = (clone $start_time)->sub($margin);
                        $end_with_margin = (clone $end_time)->add($margin);
                        
                        $is_active = ($now >= $start_with_margin && $now <= $end_with_margin);
                        $is_past = ($now > $end_with_margin);
                        
                        // Calcul des états
                        $delegue_status = isset($seance['etat_delegue']) ? 
                                         ($seance['etat_delegue'] === 'present' ? 'Présent' : 'Absent') : 
                                         ($is_past ? 'Non marqué' : '-');
                        $delegue_class = isset($seance['etat_delegue']) ? 
                                        ($seance['etat_delegue'] === 'present' ? 'present' : 'absent') : 
                                        'disabled';
                                        
                        $prof_status = isset($seance['etat_prof']) ? 
                                     ($seance['etat_prof'] === 'present' ? 'Présent' : 'Absent') : 
                                     ($is_past ? 'Non marqué' : '-');
                        $prof_class = isset($seance['etat_prof']) ? 
                                    ($seance['etat_prof'] === 'present' ? 'present' : 'absent') : 
                                    'disabled';
                                    
                        $etudiant_status = isset($seance['etat_etudiant']) ? 
                                         ($seance['etat_etudiant'] === 'present' ? 'Présent' : 'Absent') : 
                                         ($is_past ? 'Non marqué' : '-');
                        $etudiant_class = isset($seance['etat_etudiant']) ? 
                                        ($seance['etat_etudiant'] === 'present' ? 'present' : 'absent') : 
                                        'disabled';
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($seance['matiere_nom']); ?></strong>
                            <div class="text-muted"><?php echo htmlspecialchars($seance['matiere_code']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($seance['enseignant_nom']); ?></td>
                        <td><?php echo htmlspecialchars($seance['salle_nom']); ?></td>
                        <td><?php echo htmlspecialchars($seance['heure_debut']); ?></td>
                        <td><?php echo htmlspecialchars($seance['heure_fin']); ?></td>
                        <td><?php echo isset($seance['debut_reel']) ? substr($seance['debut_reel'], 0, 5) : '-'; ?></td>
                        <td><?php echo isset($seance['fin_reelle']) ? substr($seance['fin_reelle'], 0, 5) : '-'; ?></td>
                        <td>
                            <span class="status-btn <?php echo $delegue_class; ?>">
                                <?php echo $delegue_status; ?>
                            </span>
                        </td>
                        <td>
                            <span class="status-btn <?php echo $prof_class; ?>">
                                <?php echo $prof_status; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($seance['commentaires'] === 'PRESENCES_VERROUILLEES'): ?>
                                <!-- Séance verrouillée - afficher seulement le statut -->
                                <span class="status-btn <?php echo $etudiant_class; ?>">
                                    <?php echo $etudiant_status; ?>
                                </span>
                            <?php elseif ($is_active && !$is_past): ?>
                                <?php if (isset($seance['etat_etudiant'])): ?>
                                    <!-- Présence déjà marquée mais pas encore verrouillée -->
                                    <span class="status-btn <?php echo $etudiant_class; ?>">
                                        <?php echo $etudiant_status; ?>
                                    </span>
                                <?php else: ?>
                                    <!-- Séance active et non verrouillée - afficher le bouton -->
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="seance_id" value="<?php echo $seance['id']; ?>">
                                        <input type="hidden" name="status" value="present">
                                        <button type="submit" class="status-btn present">
                                            <i class="fas fa-check"></i> Présent
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?>
                                <!-- Séance passée ou inactive -->
                                <span class="status-btn <?php echo $etudiant_class; ?>">
                                    <?php echo $etudiant_status; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card">
            <h2><i class="fas fa-calendar-alt"></i> Séances du jour</h2>
            <p>Aucune séance programmée pour votre salle (<?= $_SESSION['classroom'] ?? 'Non définie' ?>) aujourd'hui (<?= $today_french ?>)</p>
            <p>Semaine ID: <?= $current_week['id'] ?></p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>