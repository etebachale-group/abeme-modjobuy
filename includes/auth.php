<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está autenticado
function isAuthenticated() {
    return isset($_SESSION['user_id']);
}

// Redirigir a login si no está autenticado
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

// Verificar autenticación para admin
function requireAdmin() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

// Obtener usuario actual
function currentUser() {
    if (isAuthenticated()) {
        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['role']
        ];
    }
    return null;
}
?>