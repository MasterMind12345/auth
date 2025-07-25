<?php
require 'includes/db.php';
include 'includes/admin-header.php';

// Gestion des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_tarif'])) {
        $niveau_id = $_POST['niveau_id'];
        $tarif_heure = $_POST['tarif_heure'];
        
        $stmt = $pdo->prepare("INSERT INTO tarifs_heures (niveau_id, tarif_heure) 
                              VALUES (:niveau_id, :tarif_heure)
                              ON DUPLICATE KEY UPDATE tarif_heure = :tarif_heure");
        $stmt->execute([':niveau_id' => $niveau_id, ':tarif_heure' => $tarif_heure]);
        
        $_SESSION['message'] = "Tarif mis à jour avec succès";
        $_SESSION['message_type'] = "success";
        header("Location: gestion_tarifs.php");
        exit();
    }
}

// Récupérer les niveaux et leurs tarifs
$tarifs = $pdo->query("SELECT n.id, n.nom, t.tarif_heure 
                       FROM niveaux n 
                       LEFT JOIN tarifs_heures t ON n.id = t.niveau_id 
                       ORDER BY n.id")->fetchAll();
?>

<div class="container py-5">
    <h1 class="text-center mb-5" style="color: #6e48aa;">
        <i class="fas fa-money-bill-wave me-2"></i> Gestion des Tarifs par Niveau
    </h1>

    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
        <?= $_SESSION['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php 
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
    endif; ?>

    <div class="card shadow-sm mb-5">
        <div class="card-body">
            <form method="post">
                <table class="table table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Niveau</th>
                            <th>Tarif par heure (FCFA)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tarifs as $tarif): ?>
                        <tr>
                            <td><?= htmlspecialchars($tarif['nom']) ?></td>
                            <td>
                                <input type="number" name="tarif_heure" class="form-control" 
                                       value="<?= $tarif['tarif_heure'] ?? 0 ?>" step="0.01" min="0" required>
                            </td>
                            <td>
                                <input type="hidden" name="niveau_id" value="<?= $tarif['id'] ?>">
                                <button type="submit" name="update_tarif" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Enregistrer
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
    </div>

    <div class="text-center">
        <a href="suivi_salaires.php" class="btn btn-success">
            <i class="fas fa-file-invoice-dollar me-2"></i> Voir le suivi des salaires
        </a>
    </div>
</div>

<?php include 'includes/admin-footer.php'; ?>