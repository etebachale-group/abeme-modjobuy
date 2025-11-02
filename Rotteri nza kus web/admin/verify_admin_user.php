<?php
// Verifica existencia y rol de un correo admin. Uso: /admin/verify_admin_user.php?email=correo
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

// Requiere sesión admin para evitar fuga de enumeración de usuarios
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'No autorizado']);
    exit;
}

$email = trim($_GET['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success'=>false,'error'=>'Email inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, email, role, first_name, last_name, created_at FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        echo json_encode(['success'=>true,'found'=>false]);
        exit;
    }
    $isAdmin = in_array($user['role'], ['admin','super_admin'], true);
    // Buscar perfil admin si existe
    $adminProfile = null;
    try {
        $adminStmt = $pdo->prepare('SELECT id, company_name, created_at FROM admins WHERE user_id = ? LIMIT 1');
        $adminStmt->execute([$user['id']]);
        $adminProfile = $adminStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {}

    echo json_encode([
        'success'=>true,
        'found'=>true,
        'isAdmin'=>$isAdmin,
        'user'=>[
            'id'=>$user['id'],
            'email'=>$user['email'],
            'role'=>$user['role'],
            'first_name'=>$user['first_name'],
            'last_name'=>$user['last_name'],
            'created_at'=>$user['created_at']
        ],
        'admin_profile'=>$adminProfile
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'DB error']);
}
