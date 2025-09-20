<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdminApi();
header('Content-Type: application/json; charset=utf-8');

try {
    $admin_id = getCurrentAdminId($pdo);
    if (!$admin_id) { http_response_code(403); echo json_encode(['success'=>false,'message'=>'No autorizado']); exit; }
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $ids = array_values(array_unique(array_map('intval', $input['item_ids'] ?? [])));
    if (empty($ids)) { echo json_encode(['success'=>true]); exit; }

    // Ensure mapping table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_seen_order_items (
        admin_id INT NOT NULL,
        order_item_id INT NOT NULL,
        seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (admin_id, order_item_id),
        INDEX(order_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Insert ignore
    $place = implode(',', array_fill(0, count($ids), '(?,?,CURRENT_TIMESTAMP)'));
    $sql = "INSERT IGNORE INTO admin_seen_order_items (admin_id, order_item_id, seen_at) VALUES $place";
    $stmt = $pdo->prepare($sql);
    $params = [];
    foreach ($ids as $id) { $params[] = $admin_id; $params[] = $id; }
    $stmt->execute($params);
    echo json_encode(['success'=>true, 'count'=>count($ids)]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
