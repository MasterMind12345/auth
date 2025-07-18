<?php 
include 'includes/db.php';

$salle_id = $_GET['id'] ?? 0;

// Récupérer les informations de la salle
$stmt = $pdo->prepare("SELECT s.*, f.nom as filiere_nom, n.nom as niveau_nom 
                      FROM salles s 
                      JOIN filieres f ON s.filiere_id = f.id 
                      JOIN niveaux n ON f.niveau_id = n.id 
                      WHERE s.id = ?");
$stmt->execute([$salle_id]);
$salle = $stmt->fetch();

// Récupérer la semaine sélectionnée pour le rapport (si elle existe)
$semaine_id = $_GET['semaine_id'] ?? 0;

// Récupérer les séances de cours pour cette salle
$stmt = $pdo->prepare("SELECT sc.*, m.nom as matiere_nom, u.name as enseignant_nom
                      FROM seances sc
                      JOIN cours c ON sc.cours_id = c.id
                      JOIN matieres m ON c.matiere_id = m.id
                      JOIN users u ON sc.enseignant_id = u.id
                      WHERE sc.salle_id = ?
                      ORDER BY sc.jour, sc.heure_debut");
$stmt->execute([$salle_id]);
$seances = $stmt->fetchAll();

// Récupérer la liste des cours disponibles
$cours = $pdo->query("SELECT c.id, m.nom as matiere_nom 
                     FROM cours c 
                     JOIN matieres m ON c.matiere_id = m.id")->fetchAll();

// Récupérer la liste des enseignants
$enseignants = $pdo->query("SELECT id, name FROM users WHERE grade = 'Enseignant'")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salle <?= htmlspecialchars($salle['nom']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .seance-card {
            background: linear-gradient(135deg, rgba(110, 72, 170, 0.2), rgba(20, 20, 20, 0.8));
            border: 1px solid #6e48aa;
            border-radius: 10px;
            margin-bottom: 15px;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .seance-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(110, 72, 170, 0.3);
        }
        .jour-section {
            margin-bottom: 30px;
            border-bottom: 1px solid #6e48aa;
            padding-bottom: 10px;
        }
        .badge-formation {
            background-color: #6e48aa;
            font-size: 0.8rem;
        }
        .btn-add-seance {
            background: linear-gradient(135deg, #6e48aa, #9b72cf);
            border: none;
            font-weight: bold;
        }
        .btn-add-seance:hover {
            background: linear-gradient(135deg, #9b72cf, #6e48aa);
        }
    </style>
</head>
<body class="admin-body">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h1 class="neon-violet">
                Salle <?= htmlspecialchars($salle['nom']) ?> 
                <span class="badge badge-formation"><?= $salle['formation'] == 'FI' ? 'Formation Initiale' : 'Formation Alternance' ?></span>
            </h1>
            <a href="filiere.php?id=<?= $salle['filiere_id'] ?>" class="btn btn-dark">Retour</a>
        </div>
        
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card bg-dark text-white">
                    <div class="card-body">
                        <h5 class="card-title">Informations</h5>
                        <p class="card-text">
                            <strong>Filière:</strong> <?= htmlspecialchars($salle['filiere_nom']) ?><br>
                            <strong>Niveau:</strong> <?= htmlspecialchars($salle['niveau_nom']) ?><br>
                            <strong>Type:</strong> <?= $salle['formation'] == 'FI' ? 'Formation Initiale' : 'Formation Alternance' ?>
                        </p>
                    </div>
                </div>
                
                <!-- Bouton pour générer le rapport -->
                <div class="card bg-dark text-white mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Générer le rapport hebdomadaire</h5>
                        <form method="get" action="generer_rapport.php" target="_blank">
                            <input type="hidden" name="salle_id" value="<?= $salle_id ?>">
                            <div class="mb-3">
                                <label for="semaine_id" class="form-label">Semaine</label>
                                <select class="form-control" id="semaine_id" name="semaine_id" required>
                                    <?php 
                                    $semaines = $pdo->query("SELECT * FROM semaines ORDER BY numero")->fetchAll();
                                    foreach ($semaines as $s): ?>
                                        <option value="<?= $s['id'] ?>" <?= $semaine_id == $s['id'] ? 'selected' : '' ?>>
                                            Semaine <?= $s['numero'] ?> (<?= $s['date_debut'] ?> au <?= $s['date_fin'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Générer le PDF</button>
                        </form>
                    </div>
                </div>

                <!-- Formulaire pour ajouter une séance -->
                <div class="card bg-dark text-white mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Ajouter une séance</h5>
                        <form id="addSeanceForm" method="post" action="add_seance.php">
                            <input type="hidden" name="salle_id" value="<?= $salle_id ?>">
                            <input type="hidden" name="semaine_id" value="<?= $semaine_id ?>">
                            
                            <div class="mb-3">
                                <label for="cours_id" class="form-label">Cours</label>
                                <select class="form-control" id="cours_id" name="cours_id" required>
                                    <?php foreach ($cours as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['matiere_nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="jour" class="form-label">Jour</label>
                                <select class="form-control" id="jour" name="jour" required>
                                    <option value="LUNDI">Lundi</option>
                                    <option value="MARDI">Mardi</option>
                                    <option value="MERCREDI">Mercredi</option>
                                    <option value="JEUDI">Jeudi</option>
                                    <option value="VENDREDI">Vendredi</option>
                                    <option value="SAMEDI">Samedi</option>
                                    <option value="DIMANCHE">Dimanche</option>
                                </select>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="heure_debut" class="form-label">Heure début</label>
                                    <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="heure_fin" class="form-label">Heure fin</label>
                                    <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="enseignant_id" class="form-label">Enseignant</label>
                                <select class="form-control" id="enseignant_id" name="enseignant_id" required>
                                    <?php foreach ($enseignants as $e): ?>
                                        <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-add-seance w-100">Ajouter la séance</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <h3 class="neon-violet mb-4">Emploi du temps</h3>
                
                <?php 
                $jours = ['LUNDI', 'MARDI', 'MERCREDI', 'JEUDI', 'VENDREDI', 'SAMEDI', 'DIMANCHE'];
                foreach ($jours as $jour): 
                    $seances_jour = array_filter($seances, function($s) use ($jour) {
                        return $s['jour'] == $jour;
                    });
                ?>
                    <div class="jour-section">
                        <h4><?= ucfirst(strtolower($jour)) ?></h4>
                        
                        <?php if (empty($seances_jour)): ?>
                            <div class="alert alert-dark">Aucune séance programmée</div>
                        <?php else: ?>
                            <?php foreach ($seances_jour as $seance): ?>
                                <div class="card seance-card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="card-title"><?= htmlspecialchars($seance['matiere_nom']) ?></h5>
                                                <p class="card-text mb-1">
                                                    <small class="text-muted"><?= substr($seance['heure_debut'], 0, 5) ?> - <?= substr($seance['heure_fin'], 0, 5) ?></small>
                                                </p>
                                                <p class="card-text">
                                                    <small>Enseignant: <?= htmlspecialchars($seance['enseignant_nom']) ?></small>
                                                </p>
                                            </div>
                                            <div>
                                                <a href="delete_seance.php?id=<?= $seance['id'] ?>&salle_id=<?= $salle_id ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cette séance?')">
                                                    <i class="bi bi-trash"></i> Supprimer
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Gestion des messages de succès/erreur
        <?php if (isset($_GET['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Succès',
                text: '<?= htmlspecialchars($_GET['success']) ?>',
                timer: 2000,
                showConfirmButton: false
            });
        <?php elseif (isset($_GET['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Erreur',
                text: '<?= htmlspecialchars($_GET['error']) ?>'
            });
        <?php endif; ?>

        // Mettre à jour la semaine sélectionnée dans le formulaire d'ajout de séance
        document.getElementById('semaine_id').addEventListener('change', function() {
            document.querySelector('input[name="semaine_id"]').value = this.value;
        });
    </script>
</body>
</html>