<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

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