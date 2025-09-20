<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cart (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id), INDEX(product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* ignore */ }

if (!isAuthenticated()) {
    echo json_encode(['success' => true, 'count' => 0]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total_items FROM cart WHERE user_id = ?");
    $stmt->execute([currentUserId()]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $count = $result['total_items'] ?? 0;
    
    echo json_encode(['success' => true, 'count' => $count]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>