<?php
// Uso temporal: /admin/one_time_check_admin.php?email=Asamshe@etebachalegroup.com
// IMPORTANTE: Borra este archivo inmediatamente después de verificar.
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

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
    $adminProfile = null;
    try {
        $ap = $pdo->prepare('SELECT id, company_name, created_at FROM admins WHERE user_id = ? LIMIT 1');
        $ap->execute([$user['id']]);
        $adminProfile = $ap->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {}
    echo json_encode([
        'success'=>true,
        'found'=>true,
        'isAdmin'=>$isAdmin,
        'user'=>$user,
        'admin_profile'=>$adminProfile,
        'warning'=>'Este script es temporal. Elimínalo.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'DB error']);
}
