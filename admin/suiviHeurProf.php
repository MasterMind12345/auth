<?php
// Activation du buffer de sortie dès le début
ob_start();

// Inclusion de la connexion DB en premier
require 'includes/db.php';

// ======================================================================
// GESTION DE L'EXPORT PDF (DOIT ÊTRE AVANT TOUTE SORTIE HTML)
// ======================================================================
if (isset($_GET['export']) && $_GET['export'] == 'pdf') {
    require_once('includes/tcpdf/tcpdf.php');
    
    // Requête pour récupérer les données
    $query = "SELECT 
                u.id, u.name, u.phone,
                SUM(TIMESTAMPDIFF(HOUR, s.heure_debut, s.heure_fin)) as heures_effectuees,
                SUM(CASE WHEN s.etat_final = 'present' THEN TIMESTAMPDIFF(HOUR, s.heure_debut, s.heure_fin) ELSE 0 END) as heures_present,
                SUM(CASE WHEN s.etat_final = 'absent' THEN TIMESTAMPDIFF(HOUR, s.heure_debut, s.heure_fin) ELSE 0 END) as heures_absent,
                GROUP_CONCAT(DISTINCT f.nom ORDER BY f.nom SEPARATOR ', ') as filieres,
                GROUP_CONCAT(DISTINCT n.nom ORDER BY n.nom SEPARATOR ', ') as niveaux
              FROM users u
              LEFT JOIN seances s ON u.id = s.enseignant_id
              LEFT JOIN cours c ON s.cours_id = c.id
              LEFT JOIN emplois_temps e ON c.emploi_id = e.id
              LEFT JOIN salles sa ON e.salle_id = sa.id
              LEFT JOIN filieres f ON sa.filiere_id = f.id
              LEFT JOIN niveaux n ON f.niveau_id = n.id
              WHERE u.grade = 'Enseignant'
              GROUP BY u.id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $enseignants = $stmt->fetchAll();

    // Création du PDF
    $pdf = new TCPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Configuration du document
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Établissement Scolaire');
    $pdf->SetTitle('Rapport des heures enseignants');
    $pdf->SetMargins(10, 20, 10);
    $pdf->AddPage();

    // Construction du HTML
    $html = '<style>
                h1 { color: #6e48aa; text-align: center; }
                table { width: 100%; border-collapse: collapse; }
                th { background-color: #6e48aa; color: white; padding: 5px; }
                td { padding: 4px; border: 1px solid #ddd; }
            </style>
            <h1>Suivi des Heures des Enseignants</h1>
            <table>
                <tr>
                    <th>Nom</th><th>Téléphone</th><th>Heures effectuées</th>
                    <th>Heures présentes</th><th>Heures absentes</th>
                    <th>Filières</th><th>Niveaux</th>
                </tr>';

    foreach ($enseignants as $enseignant) {
        $html .= '<tr>
                    <td>'.htmlspecialchars($enseignant['name']).'</td>
                    <td>'.htmlspecialchars($enseignant['phone']).'</td>
                    <td>'.($enseignant['heures_effectuees'] ?? 0).'h</td>
                    <td>'.($enseignant['heures_present'] ?? 0).'h</td>
                    <td>'.($enseignant['heures_absent'] ?? 0).'h</td>
                    <td>'.htmlspecialchars($enseignant['filieres'] ?? 'N/A').'</td>
                    <td>'.htmlspecialchars($enseignant['niveaux'] ?? 'N/A').'</td>
                </tr>';
    }

    $html .= '</table>';

    // Ajout du contenu au PDF
    $pdf->writeHTML($html, true, false, true, false, '');

    // Nettoyage du buffer et sortie
    ob_end_clean();
    $pdf->Output('rapport_heures_'.date('Y-m-d').'.pdf', 'D');
    exit();
}

// ======================================================================
// INCLUSION DU HEADER (APRÈS LE TRAITEMENT PDF)
// ======================================================================
include 'includes/admin-header.php';

// ======================================================================
// GESTION DES ACTIONS (RESET/DELETE)
// ======================================================================
if (isset($_GET['action'])) {
    $id = $_GET['id'] ?? null;
    
    if ($_GET['action'] == 'reset' && $id) {
        $stmt = $pdo->prepare("UPDATE users SET quota = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['message'] = "Heures réinitialisées avec succès";
        $_SESSION['message_type'] = "success";
    } elseif ($_GET['action'] == 'delete' && $id) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND grade = 'Enseignant'");
        $stmt->execute([$id]);
        $_SESSION['message'] = "Enseignant supprimé avec succès";
        $_SESSION['message_type'] = "success";
    }
    
    header("Location: suiviHeurProf.php");
    exit();
}

// ======================================================================
// RÉCUPÉRATION DES DONNÉES
// ======================================================================
$search = $_GET['search'] ?? '';
$query = "SELECT 
            u.id, u.name, u.phone,
            SUM(TIMESTAMPDIFF(HOUR, s.heure_debut, s.heure_fin)) as heures_effectuees,
            SUM(CASE WHEN s.etat_final = 'present' THEN TIMESTAMPDIFF(HOUR, s.heure_debut, s.heure_fin) ELSE 0 END) as heures_present,
            SUM(CASE WHEN s.etat_final = 'absent' THEN TIMESTAMPDIFF(HOUR, s.heure_debut, s.heure_fin) ELSE 0 END) as heures_absent,
            GROUP_CONCAT(DISTINCT f.nom ORDER BY f.nom SEPARATOR ', ') as filieres,
            GROUP_CONCAT(DISTINCT n.nom ORDER BY n.nom SEPARATOR ', ') as niveaux
          FROM users u
          LEFT JOIN seances s ON u.id = s.enseignant_id
          LEFT JOIN cours c ON s.cours_id = c.id
          LEFT JOIN emplois_temps e ON c.emploi_id = e.id
          LEFT JOIN salles sa ON e.salle_id = sa.id
          LEFT JOIN filieres f ON sa.filiere_id = f.id
          LEFT JOIN niveaux n ON f.niveau_id = n.id
          WHERE u.grade = 'Enseignant'";

if (!empty($search)) {
    $query .= " AND u.name LIKE :search";
}

$query .= " GROUP BY u.id ORDER BY u.name";

$stmt = $pdo->prepare($query);

if (!empty($search)) {
    $stmt->bindValue(':search', "%$search%");
}

$stmt->execute();
$enseignants = $stmt->fetchAll();
?>

<!-- ====================================================================== -->
<!-- DÉBUT DU HTML -->
<!-- ====================================================================== -->
<div class="container py-5">
    <h1 class="text-center mb-5" style="color: #6e48aa;">
        <i class="fas fa-chalkboard-teacher me-2"></i> Suivi des Heures des Enseignants
    </h1>

    <!-- Barre de recherche et bouton d'export -->
    <div class="row mb-4">
        <div class="col-md-8 mx-auto">
            <div class="d-flex justify-content-between">
                <form method="get" class="input-group shadow-sm" style="width: 70%;">
                    <input type="text" name="search" class="form-control" placeholder="Rechercher un enseignant..." value="<?= htmlspecialchars($search) ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                    <?php if (!empty($search)): ?>
                    <a href="suiviHeurProf.php" class="btn btn-outline-danger">
                        <i class="fas fa-times"></i>
                    </a>
                    <?php endif; ?>
                </form>
                <a href="?export=pdf" class="btn btn-danger ms-2">
                    <i class="fas fa-file-pdf me-2"></i> Exporter en PDF
                </a>
            </div>
        </div>
    </div>

    <!-- Messages de notification -->
    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
        <?= $_SESSION['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php 
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
    endif; ?>

    <!-- Tableau des enseignants -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Nom</th>
                            <th>Téléphone</th>
                            <th>Heures effectuées</th>
                            <th>Heures présentes</th>
                            <th>Heures absentes</th>
                            <th>Filières</th>
                            <th>Niveaux</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enseignants as $enseignant): ?>
                        <tr>
                            <td><?= htmlspecialchars($enseignant['name']) ?></td>
                            <td><?= htmlspecialchars($enseignant['phone']) ?></td>
                            <td><?= $enseignant['heures_effectuees'] ?? 0 ?>h</td>
                            <td><?= $enseignant['heures_present'] ?? 0 ?>h</td>
                            <td><?= $enseignant['heures_absent'] ?? 0 ?>h</td>
                            <td><?= htmlspecialchars($enseignant['filieres'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($enseignant['niveaux'] ?? 'N/A') ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="historique_seances.php?enseignant_id=<?= $enseignant['id'] ?>" 
                                       class="btn btn-sm btn-info" 
                                       title="Historique">
                                        <i class="fas fa-history"></i>
                                    </a>
                                    <a href="suiviHeurProf.php?action=reset&id=<?= $enseignant['id'] ?>" 
                                       class="btn btn-sm btn-warning" 
                                       title="Réinitialiser"
                                       onclick="return confirm('Confirmer la réinitialisation?')">
                                        <i class="fas fa-sync-alt"></i>
                                    </a>
                                    <a href="suiviHeurProf.php?action=delete&id=<?= $enseignant['id'] ?>" 
                                       class="btn btn-sm btn-danger" 
                                       title="Supprimer"
                                       onclick="return confirm('Confirmer la suppression?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Total Heures</h5>
                    <p class="card-text display-6">
                        <?= array_sum(array_column($enseignants, 'heures_effectuees')) ?>h
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Présences</h5>
                    <p class="card-text display-6">
                        <?= array_sum(array_column($enseignants, 'heures_present')) ?>h
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Absences</h5>
                    <p class="card-text display-6">
                        <?= array_sum(array_column($enseignants, 'heures_absent')) ?>h
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Enseignants</h5>
                    <p class="card-text display-6">
                        <?= count($enseignants) ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>