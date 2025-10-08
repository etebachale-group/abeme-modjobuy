<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

// Ensure products has is_active column for safe selects on older schemas
try { $pdo->exec("ALTER TABLE products ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1"); } catch (Exception $e) { /* ignore */ }

// Defensive tables for orders and order_items (extend orders with checkout fields/totals)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        order_number VARCHAR(50) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        full_name VARCHAR(200) NULL,
        email VARCHAR(200) NULL,
        phone VARCHAR(50) NULL,
        address TEXT NULL,
        city VARCHAR(120) NULL,
        country VARCHAR(120) NULL,
        payment_method VARCHAR(50) NULL,
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        shipping DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        taxes DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NULL,
        product_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* ignore */ }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

try {
    $pdo->beginTransaction();

    $userId = currentUserId();
    // Capture checkout fields (optional; if not provided will remain NULL)
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) { $input = $_POST; }
    $full_name = trim($input['full_name'] ?? '');
    $email = trim($input['email'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $address = trim($input['address'] ?? '');
    $city = trim($input['city'] ?? '');
    $country = trim($input['country'] ?? '');
    $payment_method = trim($input['payment_method'] ?? '');

    // Load cart items with resolved product data
    $stmt = $pdo->prepare("SELECT c.id as cart_id, p.id as product_id, p.name, p.price, COALESCE(p.is_active,1) as is_active, p.stock
                           FROM cart c JOIN products p ON p.id = c.product_id
                           WHERE c.user_id = ?");
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$items) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'El carrito está vacío.']);
        exit;
    }

    // Create order number
    $orderNumber = 'RK-' . date('YmdHis') . '-' . substr(strval($userId), -3);
    $ins = $pdo->prepare('INSERT INTO orders (user_id, order_number, status, full_name, email, phone, address, city, country, payment_method, subtotal, shipping, taxes, total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0)');
    $ins->execute([$userId, $orderNumber, 'pending', $full_name, $email, $phone, $address, $city, $country, $payment_method]);
    $orderId = (int)$pdo->lastInsertId();

    // Re-fetch cart with quantities
    $stmt = $pdo->prepare("SELECT c.product_id, c.quantity, p.name, p.price, COALESCE(p.is_active,1) as is_active, p.stock
                           FROM cart c JOIN products p ON p.id = c.product_id
                           WHERE c.user_id = ?");
    $stmt->execute([$userId]);
    $cartRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $subtotal = 0.0;
    $itIns = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price) VALUES (?, ?, ?, ?, ?)');
    foreach ($cartRows as $row) {
        if (!$row['is_active']) { continue; }
        $qty = max(1, (int)$row['quantity']);
        $price = (float)$row['price'];
        $subtotal += $price * $qty;
        $itIns->execute([$orderId, $row['product_id'], $row['name'], $qty, $price]);
        // decrement stock if managed
        if (isset($row['stock']) && $row['stock'] !== null) {
            $dec = $pdo->prepare('UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE id = ?');
            $dec->execute([$qty, $row['product_id']]);
        }
    }

    // Shipping/taxes calculation (same placeholder as get_cart_summary)
    $shipping = $subtotal > 0 ? 5.00 : 0.00;
    $taxes = 0.00;
    $total = $subtotal + $shipping + $taxes;

    // Update order totals
    $up = $pdo->prepare('UPDATE orders SET subtotal = ?, shipping = ?, taxes = ?, total = ? WHERE id = ?');
    $up->execute([round($subtotal,2), round($shipping,2), round($taxes,2), round($total,2), $orderId]);

    // Clear cart
    $del = $pdo->prepare('DELETE FROM cart WHERE user_id = ?');
    $del->execute([$userId]);

    // Notify user about order creation
    try {
        $n = $pdo->prepare('INSERT INTO notifications (user_id, title, message, link, is_read) VALUES (?, ?, ?, ?, 0)');
        $n->execute([
            $userId,
            'Pedido creado',
            'Tu pedido ' . $orderNumber . ' ha sido creado y está pendiente de confirmación.',
            '../order-confirmation.php?order=' . urlencode($orderNumber)
        ]);
    } catch (Exception $e) { /* ignore notification errors */ }

    $pdo->commit();
    echo json_encode(['success' => true, 'order_id' => $orderId, 'order_number' => $orderNumber, 'total' => round($total,2)]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'No se pudo crear el pedido: ' . $e->getMessage()]);
}
