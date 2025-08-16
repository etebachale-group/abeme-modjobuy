<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT p.price, c.quantity FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
    $stmt->execute([currentUserId()]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $subtotal = 0;
    foreach ($cartItems as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }

    // For now, shipping and taxes are 0
    $shipping = 0;
    $taxes = 0;
    $total = $subtotal + $shipping + $taxes;

    echo json_encode([
        'success' => true,
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'taxes' => $taxes,
        'total' => $total
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>