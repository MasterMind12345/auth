<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    if (isStudent()) {
        header('Location: dashEtudiant.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

$error = '';
if (isset($_GET['error'])) {
    $error = $_GET['error'] === 'pending' ? 'Votre compte est en attente de validation' : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $grade = trim($_POST['grade']);
    $password = trim($_POST['password']);
    
    if (empty($name) || empty($grade) || empty($password)) {
        $error = 'Tous les champs sont obligatoires';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE name = ? AND grade = ?");
        $stmt->execute([$name, $grade]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['grade'] = $user['grade'];
            $_SESSION['validated'] = $user['validated'];
            
            // Ajout de la salle de classe dans la session si l'utilisateur est délégué ou étudiant
            if ($user['grade'] === 'Delegue' || $user['grade'] === 'Etudiant') {
                $_SESSION['classroom'] = $user['classroom'];
                
                // Récupération des informations supplémentaires sur la salle
                $stmt = $pdo->prepare("SELECT f.nom as filiere_nom 
                                      FROM salles s 
                                      JOIN filieres f ON s.filiere_id = f.id 
                                      WHERE s.nom = ?");
                $stmt->execute([$user['classroom']]);
                $salle_info = $stmt->fetch();
                
                if ($salle_info) {
                    $_SESSION['filiere'] = $salle_info['filiere_nom'];
                }
            }
            
            if ($user['grade'] === 'Delegue') {
                if ($user['validated'] === 'none') {
                    header('Location: validation.php');
                } elseif ($user['validated'] === 'pending') {
                    session_destroy();
                    header('Location: index.php?error=pending');
                } else {
                    header('Location: dashboard.php');
                }
            } elseif ($user['grade'] === 'Etudiant') {
                header('Location: dashEtudiant.php');
            } else {
                header('Location: dashboard.php');
            }
            exit();
        } else {
            $error = 'Identifiants incorrects';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <link rel="stylesheet" href="includes/style.css">
    <style>
        :root {
            --violet-dark: #2a0a42;
            --violet-medium: #4b2a70;
            --violet-light: #7b4b9e;
            --violet-neon: #b388ff;
            --blanc: #ffffff;
            --noir: #0a0a0a;
        }
        
        body {
            background: linear-gradient(135deg, var(--violet-dark), var(--noir));
            color: var(--blanc);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        
        .auth-container {
            background: rgba(10, 10, 10, 0.8);
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            border: 1px solid var(--violet-medium);
        }
        
        h1 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: var(--violet-neon);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--violet-neon);
        }
        
        input, select {
            width: 100%;
            padding: 0.75rem;
            border-radius: 4px;
            border: 1px solid var(--violet-medium);
            background: rgba(10, 10, 10, 0.5);
            color: var(--blanc);
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--violet-neon);
        }
        
        button {
            width: 100%;
            padding: 0.75rem;
            background: var(--violet-light);
            color: var(--blanc);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        
        button:hover {
            background: var(--violet-neon);
            transform: translateY(-2px);
        }
        
        .error {
            color: #ff6b6b;
            margin-bottom: 1rem;
            padding: 0.75rem;
            background: rgba(255, 0, 0, 0.1);
            border-radius: 4px;
            border: 1px solid #ff6b6b;
            text-align: center;
        }
        
        @media (max-width: 480px) {
            .auth-container {
                margin: 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1>Connexion</h1>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form action="index.php" method="POST">
            <div class="form-group">
                <label for="name">Nom</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="form-group">
                <label for="grade">Grade</label>
                <select id="grade" name="grade" required>
                    <option value="">Sélectionnez un grade</option>
                    <option value="Delegue">Délégué</option>
                    <option value="Enseignant">Enseignant</option>
                    <option value="Etudiant">Étudiant</option>
                </select>
            </div>
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Se connecter</button>
        </form>
        <p style="text-align: center; margin-top: 20px; color: var(--violet-neon);">
            Pas encore inscrit? <a href="register.php" style="color: var(--blanc);">Créer un compte</a>
        </p>
    </div>
</body>
</html>