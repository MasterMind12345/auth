<?php include 'includes/db.php'; ?>
<style>
    .btn-neon-orange {
        background: linear-gradient(90deg, #ff7e5f 0%, #feb47b 100%);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 30px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s;
        box-shadow: 0 0 15px rgba(255, 126, 95, 0.5);
    }

    .btn-neon-orange:hover {
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 0 20px rgba(255, 126, 95, 0.8);
    }

    .btn-neon-purple {
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

    .btn-neon-purple:hover {
        color: white;
        transform: translateY(-3px);
        box-shadow: 0 0 20px rgba(110, 72, 170, 0.8);
    }

    .neon-blue {
        color: #fff;
        text-shadow: 0 0 10px #2575fc, 0 0 20px #2575fc, 0 0 30px #2575fc;
    }
</style>
<?php include 'includes/admin-header.php'; ?>

<div class="container">
    <h1 class="text-center mb-5" style="color: #6e48aa;">Administration des Niveaux</h1>
    
    <div class="d-flex justify-content-center mb-4 gap-3">
        <a href="suiviHeurProf.php" class="btn btn-neon-orange me-2">
            <i class="fas fa-chalkboard-teacher me-2"></i> Suivi Heures Profs
        </a>
        <a href="presence.php" class="btn btn-neon-purple">
            <i class="fas fa-clipboard-list me-2"></i> Liste des Présences
        </a>
    </div>
    
    <div class="row">
        <?php
        $stmt = $pdo->query("SELECT * FROM niveaux");
        while ($niveau = $stmt->fetch()):
        ?>
        <div class="col-md-4 mb-4">
            <div class="card niveau-card" style="border: 1px solid #6e48aa; border-radius: 10px; transition: transform 0.3s;">
                <div class="card-body text-center">
                    <h3 class="card-title" style="color: #6e48aa;"><?= htmlspecialchars($niveau['nom']) ?></h3>
                    <a href="niveau.php?id=<?= $niveau['id'] ?>" class="btn btn-primary">Voir les filières</a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>