<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
requireAdminApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

if (!csrf_validate($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
$status = trim($_POST['status'] ?? '');

if ($orderId <= 0 || $status === '') {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$allowed = ['pending','confirmed','processing','shipped','delivered','cancelled'];
if (!in_array($status, $allowed, true)) {
    echo json_encode(['success' => false, 'message' => 'Estado no permitido']);
    exit;
}

try {
    // Verify this order includes items from products owned by current admin
    $admin_id = getCurrentAdminId($pdo);
    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt
        FROM order_items oi
        WHERE oi.order_id = ? AND oi.product_id IN (SELECT id FROM products WHERE admin_id = ?)");
    $stmt->execute([$orderId, $admin_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['cnt'] === 0) {
        echo json_encode(['success' => false, 'message' => 'No autorizado para este pedido']);
        exit;
    }

    $up = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $up->execute([$status, $orderId]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

?>
