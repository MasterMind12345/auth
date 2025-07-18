<?php 
include 'includes/db.php';
$niveau_id = $_GET['id'] ?? 0;

// Récupérer le niveau
$stmt = $pdo->prepare("SELECT * FROM niveaux WHERE id = ?");
$stmt->execute([$niveau_id]);
$niveau = $stmt->fetch();

// Récupérer les filières
$stmt = $pdo->prepare("SELECT * FROM filieres WHERE niveau_id = ?");
$stmt->execute([$niveau_id]);
$filieres = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - <?= htmlspecialchars($niveau['nom']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="admin-body">
    <div class="container py-5">
        <h1 class="text-center mb-5 neon-violet"><?= htmlspecialchars($niveau['nom']) ?></h1>
        
        <div class="row">
            <?php foreach ($filieres as $filiere): ?>
            <div class="col-md-4 mb-4">
                <div class="card filiere-card">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?= htmlspecialchars($filiere['nom']) ?></h3>
                        <a href="filiere.php?id=<?= $filiere['id'] ?>" class="btn btn-violet">Voir les salles</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-4">
            <a href="create_filiere.php?niveau_id=<?= $niveau_id ?>" class="btn btn-neon">Créer une filière</a>
            <a href="index.php" class="btn btn-dark">Retour</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>