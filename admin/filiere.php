<?php 
include 'includes/db.php';
$filiere_id = $_GET['id'] ?? 0;

// Récupérer la filière
$stmt = $pdo->prepare("SELECT f.*, n.nom as niveau_nom FROM filieres f JOIN niveaux n ON f.niveau_id = n.id WHERE f.id = ?");
$stmt->execute([$filiere_id]);
$filiere = $stmt->fetch();

// Récupérer les salles
$stmt = $pdo->prepare("SELECT * FROM salles WHERE filiere_id = ?");
$stmt->execute([$filiere_id]);
$salles = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?= htmlspecialchars($filiere['nom']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-body">
    <div class="container py-5">
        <h1 class="text-center mb-5 neon-violet"><?= htmlspecialchars($filiere['niveau_nom']) ?> - <?= htmlspecialchars($filiere['nom']) ?></h1>
        
  <div class="row">
    <?php foreach ($salles as $salle): ?>
    <div class="col-md-4 mb-4">
        <div class="card salle-card">
            <div class="card-body text-center">
                <h3 class="card-title">Salle <?= htmlspecialchars($salle['nom']) ?></h3>
                <p class="card-text">
                    <span class="badge bg-<?= $salle['formation'] == 'FI' ? 'primary' : 'warning' ?>">
                        <?= $salle['formation'] == 'FI' ? 'FI' : 'FA' ?>
                    </span>
                </p>
                <a href="salle_detail.php?id=<?= $salle['id'] ?>" class="btn btn-violet">Voir détails</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

        <div class="text-center mt-4">
            <a href="create_salle.php?filiere_id=<?= $filiere_id ?>" class="btn btn-neon">Créer une salle</a>
            <a href="niveau.php?id=<?= $filiere['niveau_id'] ?>" class="btn btn-dark">Retour</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>