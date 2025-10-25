<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
    // For long-lived streams (EventSource) release the session lock ASAP
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (stripos($accept, 'text/event-stream') !== false || preg_match('/\/stream\.php(\?|$)/i', $uri)) {
        // We only need to read session, not write, in SSE endpoints
        @session_write_close();
    }
}

// Try to include a shared auth library if present (optional)
$sharedAuth = __DIR__ . '/../../includes/auth.php';
if (file_exists($sharedAuth)) {
    require_once $sharedAuth;
}

// Minimal local auth helpers if not provided by shared library
if (!function_exists('isAuthenticated')) {
    function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
}

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
        // dynamic table resolution
        try { require_once __DIR__ . '/admin_table.php'; } catch (Exception $e) {}
        $table = 'admins';
        if (function_exists('admin_table') && isset($pdo)) {
            try { $table = admin_table($pdo); } catch (Exception $e) {}
        }
        try {
            $stmt = $pdo->prepare("SELECT id FROM `{$table}` WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            return $admin ? $admin['id'] : null;
        } catch (Exception $e) { return null; }
    }
}

// Admin enforcement helpers used by admin endpoints/pages
if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        if (!isAuthenticated() || !isAdmin()) {
            header('Location: ../login.php');
            exit;
        }
    }
}

if (!function_exists('requireAdminApi')) {
    function requireAdminApi() {
        header('Content-Type: application/json');
        if (!isAuthenticated() || !isAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            exit;
        }
    }
}

// Minimal CSRF helpers (per-request token via session)
if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_validate')) {
    function csrf_validate($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
    }
}

// Require authentication for protected pages
if (!function_exists('requireAuth')) {
    function requireAuth() {
        if (!isAuthenticated()) {
            header('Location: login.php');
            exit;
        }
    }
}
?>