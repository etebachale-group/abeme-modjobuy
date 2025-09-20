<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $item_id = $data['item_id'] ?? null;

    if (!$item_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid input.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$item_id, currentUserId()]);

        if ($stmt->rowCount() > 0) {
            $c = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) as total_items FROM cart WHERE user_id = ?');
            $c->execute([currentUserId()]);
            $count = (int)($c->fetch(PDO::FETCH_ASSOC)['total_items'] ?? 0);
            echo json_encode(['success' => true, 'message' => 'Item removed from cart.', 'count' => $count]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Cart item not found or permission denied.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>