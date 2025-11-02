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

    $subtotal = 0.0;
    foreach ($cartItems as $item) {
        $price = (float)$item['price'];
        $qty = (int)$item['quantity'];
        $subtotal += $price * $qty;
    }

    // Placeholder rules (can be replaced by admin-configurable settings)
    // Shipping: flat rate if subtotal > 0
    $shipping = $subtotal > 0 ? 5.00 : 0.00;
    // Taxes: simple 0% for now (placeholder)
    $taxes = 0.00;

    $total = $subtotal + $shipping + $taxes;

    echo json_encode([
        'success' => true,
        'subtotal' => round($subtotal, 2),
        'shipping' => round($shipping, 2),
        'taxes' => round($taxes, 2),
        'total' => round($total, 2)
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>