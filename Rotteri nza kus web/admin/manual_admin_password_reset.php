<?php
/**
 * manual_admin_password_reset.php
 * ------------------------------------------------------------------
 * Uso temporal: Forzar cambio de contraseña para un email admin conocido
 * mediante un token secreto. Devuelve JSON. Borrar después.
 *
 * POST params:
 *   token    (string) Debe coincidir con TOKEN_RESET_PASS
 *   email    (string) Email del usuario admin
 *   password (string) Nueva contraseña (mín 8 chars)
 */
header('Content-Type: application/json; charset=utf-8');

const TOKEN_RESET_PASS = 'CAMBIA_ESTE_TOKEN'; // Sustituir por un token aleatorio

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Método no permitido']);
    exit;
}

$token    = $_POST['token']    ?? '';
$email    = trim($_POST['email'] ?? '');
$password = (string)($_POST['password'] ?? '');

if (!hash_equals(TOKEN_RESET_PASS, $token)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Token inválido']);
    exit;
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'Email inválido']);
    exit;
}
if (strlen($password) < 8) {
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'Contraseña mínima 8 caracteres']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE email=?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'Usuario no encontrado']);
        exit;
    }
    if ($user['role'] !== 'admin') {
        // Elevar a admin si no lo es (para tu propósito de administración)
        $pdo->prepare('UPDATE users SET role="admin" WHERE id=?')->execute([$user['id']]);
    }
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>10]);
    $upd = $pdo->prepare('UPDATE users SET password=? WHERE id=?');
    $upd->execute([$hash, $user['id']]);
    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>'Password actualizado','user_id'=>$user['id']]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error al actualizar','error'=>$e->getMessage()]);
}

// BORRAR ESTE ARCHIVO TRAS SU USO
?>
