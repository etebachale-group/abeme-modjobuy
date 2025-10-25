<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
requireAdminApi();

$orderId = (int)($_GET['order_id'] ?? 0);
if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
    exit;
}

// Ensure products.source_url exists defensively
try { $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS source_url VARCHAR(500) NULL"); } catch (Exception $e) { /* ignore */ }

try {
    $admin_id = getCurrentAdminId($pdo);

    // Verify at least one item belongs to this admin
    $chk = $pdo->prepare("SELECT COUNT(*) AS cnt FROM order_items oi WHERE oi.order_id = ? AND oi.product_id IN (SELECT id FROM products WHERE admin_id = ?)");
    $chk->execute([$orderId, $admin_id]);
    $ok = (int)($chk->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0) > 0;
    if (!$ok) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    // Header
    $h = $pdo->prepare("SELECT o.id, o.order_number, o.status, o.created_at, u.first_name, u.last_name, u.email
                        FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?");
    $h->execute([$orderId]);
    $header = $h->fetch(PDO::FETCH_ASSOC) ?: [];

    // Items owned by this admin, include source_url
    $it = $pdo->prepare("SELECT oi.id, oi.product_id, oi.product_name, oi.quantity, oi.unit_price, p.source_url
                         FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
                         WHERE oi.order_id = ? AND (oi.product_id IN (SELECT id FROM products WHERE admin_id = ?) OR oi.product_id IS NULL)");
    $it->execute([$orderId, $admin_id]);
    $items = $it->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'order' => $header, 'items' => $items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
