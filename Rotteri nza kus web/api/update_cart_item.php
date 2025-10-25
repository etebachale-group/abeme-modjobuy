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
    $quantity = $data['quantity'] ?? null;

    if (!$item_id || !$quantity || !is_numeric($quantity) || $quantity < 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid input.']);
        exit;
    }

    try {
        // Ensure products has stock column
        try { $pdo->exec("ALTER TABLE products ADD COLUMN stock INT NULL"); } catch (Exception $e) {}

        // Fetch product for this cart item and validate stock if managed
        $pstmt = $pdo->prepare("SELECT p.stock FROM cart c JOIN products p ON p.id = c.product_id WHERE c.id = ? AND c.user_id = ?");
        $pstmt->execute([$item_id, currentUserId()]);
        $prow = $pstmt->fetch(PDO::FETCH_ASSOC);
        if ($prow && $prow['stock'] !== null) {
            $available = max(0, (int)$prow['stock']);
            if ($quantity > $available) {
                $quantity = $available;
            }
            if ($quantity <= 0) {
                // Remove item if no stock
                $del = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $del->execute([$item_id, currentUserId()]);
                echo json_encode(['success' => true, 'message' => 'Sin stock, se eliminó del carrito', 'count' => 0]);
                return;
            }
        }

        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$quantity, $item_id, currentUserId()]);

        if ($stmt->rowCount() > 0) {
            $c = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) as total_items FROM cart WHERE user_id = ?');
            $c->execute([currentUserId()]);
            $count = (int)($c->fetch(PDO::FETCH_ASSOC)['total_items'] ?? 0);
            echo json_encode(['success' => true, 'message' => 'Cart updated successfully.', 'count' => $count]);
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