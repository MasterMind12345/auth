<?php
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isTeacher() {
    return isset($_SESSION['grade']) && $_SESSION['grade'] === 'Enseignant';
}

function isDelegate() {
    return isset($_SESSION['grade']) && $_SESSION['grade'] === 'Delegue' 
           && isset($_SESSION['classroom']) && !empty($_SESSION['classroom']);
}

function isStudent() {
    return isset($_SESSION['grade']) && $_SESSION['grade'] === 'Etudiant' 
           && isset($_SESSION['classroom']) && !empty($_SESSION['classroom']);
}

function redirectIfNotValidated() {
    if (isDelegate()) {
        if ($_SESSION['validated'] === 'none') {
            header('Location: validation.php');
            exit();
        } elseif ($_SESSION['validated'] !== 'yes') {
            session_destroy();
            header('Location: index.php?error=pending');
            exit();
        }
    }
}
?>