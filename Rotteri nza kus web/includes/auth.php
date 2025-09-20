<?php
// Wrapper: unify with root auth system
require_once __DIR__ . '/../../includes/auth.php';

// Provide backward-compatible helpers expected by Rotteri pages
if (!function_exists('currentUserId')) {
    function currentUserId() {
        return isAuthenticated() ? ($_SESSION['user_id'] ?? null) : null;
    }
}

if (!function_exists('currentUserRole')) {
    function currentUserRole() {
        return isAuthenticated() ? ($_SESSION['role'] ?? null) : null;
    }
}

if (!function_exists('redirectIfAuthenticated')) {
    function redirectIfAuthenticated() {
        if (isAuthenticated()) {
            header('Location: index.php');
            exit();
        }
    }
}

if (!function_exists('redirectIfAdmin')) {
    function redirectIfAdmin() {
        if (isAdmin()) {
            header('Location: admin/index.php');
            exit();
        }
    }
}

// Admin ID lookup remains specific to this sub-app
if (!function_exists('getCurrentAdminId')) {
    function getCurrentAdminId($pdo) {
        if (!isAuthenticated() || !isAdmin()) {
            return null;
        }
        try {
            $stmt = $pdo->prepare('SELECT id FROM admins WHERE user_id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            return $admin ? $admin['id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }
}
?>