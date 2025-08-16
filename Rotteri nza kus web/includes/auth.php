<?php
session_start();

// Check if user is authenticated
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isAuthenticated() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Get current user ID
function currentUserId() {
    return isAuthenticated() ? $_SESSION['user_id'] : null;
}

// Get current user role
function currentUserRole() {
    return isAuthenticated() ? $_SESSION['role'] : null;
}

// Get current user name
function currentUserName() {
    return isAuthenticated() ? $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] : null;
}

// Redirect to login if not authenticated
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit();
    }
}

// Redirect to home if already authenticated
function redirectIfAuthenticated() {
    if (isAuthenticated()) {
        header('Location: index.php');
        exit();
    }
}

// Redirect to admin panel if user is admin
function redirectIfAdmin() {
    if (isAdmin()) {
        header('Location: admin/index.php');
        exit();
    }
}

// Check if user is admin and redirect if not
function requireAdmin() {
    if (!isAdmin()) {
        header('Location: index.php');
        exit();
    }
}

// Get admin ID for current user
function getCurrentAdminId($pdo) {
    if (!isAuthenticated() || !isAdmin()) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $admin ? $admin['id'] : null;
}
?>