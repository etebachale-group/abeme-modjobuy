<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
requireAdminApi();

// Ensure source_url exists
try { $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS source_url VARCHAR(500) NULL"); } catch (Exception $e) { }
// Ensure admin_seen_order_items exists (server-side persistence of seen state)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_seen_order_items (
        admin_id INT NOT NULL,
        order_item_id INT NOT NULL,
        seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (admin_id, order_item_id),
        INDEX(order_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { }

try {
    $admin_id = getCurrentAdminId($pdo);
    if (!$admin_id) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'No autorizado']); exit; }
    $stmt = $pdo->prepare("SELECT o.id, o.order_number, o.status, o.created_at,
                      oi.id AS item_id, oi.product_name, oi.quantity, oi.unit_price,
                      u.first_name, u.last_name,
                      p.source_url,
                      CASE WHEN s.order_item_id IS NULL THEN 0 ELSE 1 END AS is_seen
                  FROM orders o
                  JOIN order_items oi ON o.id = oi.order_id
                  JOIN users u ON o.user_id = u.id
                  LEFT JOIN products p ON oi.product_id = p.id
                  LEFT JOIN admin_seen_order_items s ON s.admin_id = ? AND s.order_item_id = oi.id
                  WHERE oi.product_id IN (SELECT id FROM products WHERE admin_id = ?)
                  ORDER BY o.created_at DESC, o.id DESC");
    $stmt->execute([$admin_id, $admin_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success'=>true,'orders'=>$rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
