<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --violet-neon: #bc13fe;
            --dark-bg: #121212;
            --light-bg: #ffffff;
        }
        
        body {
            background-color: var(--light-bg);
            color: #333;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #6e48aa 0%, #9d50bb 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }
        
        .nav-link {
            color: white !important;
            font-weight: 500;
            margin: 0 10px;
            transition: all 0.3s;
        }
        
        .nav-link:hover {
            color: var(--violet-neon) !important;
            transform: translateY(-2px);
        }
        
        .badge-pending {
            background-color: #ffc107;
            color: #000;
        }
        
        .badge-teacher {
            background-color: #17a2b8;
            color: white;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .table th {
            background-color: #6e48aa;
            color: white;
        }
        
        .btn-validate {
            background-color: #28a745;
            border: none;
        }
        
        .btn-reject {
            background-color: #dc3545;
            border: none;
        }
        
        .admin-container {
            padding: 2rem;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 30px rgba(0,0,0,0.05);
            margin-top: 2rem;
        }
        
        .dropdown-menu-wide {
            width: 600px;
            padding: 0;
        }
        
        .tab-content {
            padding: 15px;
        }
        
        .nav-tabs .nav-link {
            color: #495057 !important;
        }
        
        .nav-tabs .nav-link.active {
            color: #6e48aa !important;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <header class="admin-header">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-dark">
                <div class="container-fluid">
                    <a class="navbar-brand" href="index.php">Admin Panel</a>
                    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="adminNav">
                        <ul class="navbar-nav me-auto">
                            <li class="nav-item">
                                <a class="nav-link" href="index.php">Niveaux</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="designation.php">Validation Délégués</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="validation_enseignant.php">Validation Enseignants</a>
                            </li>
                        </ul>
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="pendingDropdown" role="button" data-bs-toggle="dropdown">
                                    Demandes en attente
                                    <?php 
                                    $pendingDelegatesCount = $pdo->query("SELECT COUNT(*) FROM users WHERE grade = 'Delegue' AND validated = 'pending'")->fetchColumn();
                                    $pendingTeachersCount = $pdo->query("SELECT COUNT(*) FROM users WHERE grade = 'Enseignant' AND validated = 'pending'")->fetchColumn();
                                    $totalPending = $pendingDelegatesCount + $pendingTeachersCount;
                                    if ($totalPending > 0): ?>
                                        <span class="badge badge-pending ms-1"><?= $totalPending ?></span>
                                    <?php endif; ?>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end dropdown-menu-wide" aria-labelledby="pendingDropdown">
                                    <div class="p-0">
                                        <ul class="nav nav-tabs" id="pendingTabs" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link active" id="delegates-tab" data-bs-toggle="tab" data-bs-target="#delegates" type="button" role="tab">
                                                    Délégués
                                                    <?php if ($pendingDelegatesCount > 0): ?>
                                                        <span class="badge badge-pending ms-1"><?= $pendingDelegatesCount ?></span>
                                                    <?php endif; ?>
                                                </button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="teachers-tab" data-bs-toggle="tab" data-bs-target="#teachers" type="button" role="tab">
                                                    Enseignants
                                                    <?php if ($pendingTeachersCount > 0): ?>
                                                        <span class="badge badge-teacher ms-1"><?= $pendingTeachersCount ?></span>
                                                    <?php endif; ?>
                                                </button>
                                            </li>
                                        </ul>
                                        <div class="tab-content p-3">
                                            <div class="tab-pane fade show active" id="delegates" role="tabpanel">
                                                <?php
                                                $pendingDelegates = $pdo->query("SELECT * FROM users WHERE grade = 'Delegue' AND validated = 'pending' LIMIT 3")->fetchAll();
                                                if (empty($pendingDelegates)): ?>
                                                    <p class="text-muted small mb-0">Aucun délégué en attente</p>
                                                <?php else: ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover">
                                                            <thead>
                                                                <tr>
                                                                    <th>Nom</th>
                                                                    <th>Salle</th>
                                                                    <th>Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($pendingDelegates as $delegate): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($delegate['name']) ?></td>
                                                                    <td><?= htmlspecialchars($delegate['classroom'] ?? 'N/A') ?></td>
                                                                    <td>
                                                                        <a href="validate.php?id=<?= $delegate['id'] ?>&action=yes" class="btn btn-sm btn-validate">Valider</a>
                                                                        <a href="validate.php?id=<?= $delegate['id'] ?>&action=no" class="btn btn-sm btn-reject">Refuser</a>
                                                                    </td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <?php if ($pendingDelegatesCount > 3): ?>
                                                        <div class="text-end mt-2">
                                                            <a href="designation.php" class="btn btn-sm btn-primary">Voir tout (<?= $pendingDelegatesCount ?>)</a>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="tab-pane fade" id="teachers" role="tabpanel">
                                                <?php
                                                $pendingTeachers = $pdo->query("SELECT * FROM users WHERE grade = 'Enseignant' AND validated = 'pending' LIMIT 3")->fetchAll();
                                                if (empty($pendingTeachers)): ?>
                                                    <p class="text-muted small mb-0">Aucun enseignant en attente</p>
                                                <?php else: ?>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover">
                                                            <thead>
                                                                <tr>
                                                                    <th>Nom</th>
                                                                    <th>Téléphone</th>
                                                                    <th>Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($pendingTeachers as $teacher): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($teacher['name']) ?></td>
                                                                    <td><?= htmlspecialchars($teacher['phone']) ?></td>
                                                                    <td>
                                                                        <a href="validate_teacher.php?id=<?= $teacher['id'] ?>&action=yes" class="btn btn-sm btn-validate">Valider</a>
                                                                        <a href="validate_teacher.php?id=<?= $teacher['id'] ?>&action=no" class="btn btn-sm btn-reject">Refuser</a>
                                                                    </td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <?php if ($pendingTeachersCount > 3): ?>
                                                        <div class="text-end mt-2">
                                                            <a href="validation_enseignant.php" class="btn btn-sm btn-primary">Voir tout (<?= $pendingTeachersCount ?>)</a>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../logout.php">Déconnexion</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <main class="container admin-container">