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
                        </ul>
                        <ul class="navbar-nav ms-auto">
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="pendingDropdown" role="button" data-bs-toggle="dropdown">
                                    Demandes en attente
                                    <?php 
                                    $pendingCount = $pdo->query("SELECT COUNT(*) FROM users WHERE grade = 'Delegue' AND validated = 'pending'")->fetchColumn();
                                    if ($pendingCount > 0): ?>
                                        <span class="badge badge-pending ms-1"><?= $pendingCount ?></span>
                                    <?php endif; ?>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="pendingDropdown" style="width: 600px; padding: 0;">
                                    <div class="p-3">
                                        <h6 class="dropdown-header">Délégués à valider</h6>
                                        <?php
                                        $pendingDelegates = $pdo->query("SELECT * FROM users WHERE grade = 'Delegue' AND validated = 'pending' LIMIT 3")->fetchAll();
                                        if (empty($pendingDelegates)): ?>
                                            <p class="text-muted small mb-0">Aucune demande en attente</p>
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
                                            <?php if ($pendingCount > 3): ?>
                                                <div class="text-end mt-2">
                                                    <a href="designation.php" class="btn btn-sm btn-primary">Voir tout (<?= $pendingCount ?>)</a>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
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