<?php
if (session_status() === PHP_SESSION_NONE) {
    if (PHP_SAPI !== 'cli' && !defined('NO_SESSION')) {
        session_start();
    }
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

// For API endpoints: respond with JSON instead of redirecting
function requireAuthApi() {
    if (!isAuthenticated()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
}

// Verificar autenticación para admin
function requireAdmin() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
    $role = $_SESSION['role'] ?? 'user';
    if ($role !== 'admin' && $role !== 'super_admin') {
        http_response_code(403);
        echo 'Acceso de administrador requerido';
        exit;
    }
}

// Requerir super administrador
function requireSuperAdmin() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
    $role = $_SESSION['role'] ?? 'user';
    if ($role !== 'super_admin') {
        http_response_code(403);
        echo 'Acceso de super administrador requerido';
        exit;
    }
}

// For API endpoints requiring super admin
function requireSuperAdminApi() {
    if (!isAuthenticated()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
    $role = $_SESSION['role'] ?? 'user';
    if ($role !== 'super_admin') {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso de super administrador requerido']);
        exit;
    }
}

// Enforce that the current user can access only their own partner wallet unless admin
function requirePartnerAccess($partnerName) {
    if (!isAuthenticated()) { header('Location: login.php'); exit; }
    $role = $_SESSION['role'] ?? 'user';
    // Only super_admin can access any partner; admins restricted to own partner
    if ($role === 'super_admin') return;
    $own = $_SESSION['partner_name'] ?? '';
    if (strcasecmp((string)$own, (string)$partnerName) !== 0) {
        http_response_code(403);
        echo 'Acceso denegado';
        exit;
    }
}

// API variant: JSON responses
function requirePartnerAccessApi($partnerName) {
    if (!isAuthenticated()) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
    $role = $_SESSION['role'] ?? 'user';
    if ($role === 'super_admin') return;
    $own = $_SESSION['partner_name'] ?? '';
    if (strcasecmp((string)$own, (string)$partnerName) !== 0) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
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

// Helper: check if admin (admin or super_admin)
function isAdmin() {
    if (!isAuthenticated()) return false;
    $role = $_SESSION['role'] ?? 'user';
    return $role === 'admin' || $role === 'super_admin';
}

// Helper: current user display name (fallback to email)
function currentUserName() {
    if (!isAuthenticated()) return '';
    $first = $_SESSION['first_name'] ?? '';
    $last = $_SESSION['last_name'] ?? '';
    $name = trim($first . ' ' . $last);
    if ($name === '') {
        return $_SESSION['user_email'] ?? 'Usuario';
    }
    return $name;
}

// --- Extras compartidos y utilidades ---

// Asegurar sesión iniciada (por si se usa desde CLI/tests)
function ensure_session_started() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Alias común para exigir login en páginas
function requireLogin() { requireAuth(); }

// Comprobar si es super admin
function isSuperAdmin() {
    if (!isAuthenticated()) return false;
    return ($_SESSION['role'] ?? 'user') === 'super_admin';
}

// Variación para APIs: exige admin y responde en JSON
function requireAdminApi() {
    if (!isAuthenticated()) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autenticado']);
        exit;
    }
    if (!isAdmin()) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso de administrador requerido']);
        exit;
    }
}

// CSRF utilities (token por sesión)
function csrf_token() {
    ensure_session_started();
    if (empty($_SESSION['csrf_token'])) {
        // 32 bytes -> 64 hex chars
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_validate($token) {
    ensure_session_started();
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($token) && $token !== '' && $sessionToken !== '' && hash_equals($sessionToken, $token);
}
?>