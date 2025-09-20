<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'Por favor inicie sesión para agregar productos al carrito.']);
    exit;
}

// Ensure cart table exists (defensive)
try { $pdo->exec("CREATE TABLE IF NOT EXISTS cart (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, product_id INT NOT NULL, quantity INT NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(user_id), INDEX(product_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $product_id = $data['product_id'] ?? null;
    $quantity = max(1, (int)($data['quantity'] ?? 1));
    
    if (!$product_id) {
        echo json_encode(['success' => false, 'message' => 'ID de producto es requerido']);
        exit;
    }
    
    try {
        // Check if product exists and is active (handle missing is_active as active)
        $stmt = $pdo->prepare("SELECT id, COALESCE(is_active,1) as active FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product || (isset($product['active']) && (int)$product['active'] !== 1)) {
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
            exit;
        }
        
        // Check if item already in cart
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([currentUserId(), $product_id]);
        $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cartItem) {
            // Update quantity
            $newQuantity = $cartItem['quantity'] + $quantity;
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmt->execute([$newQuantity, $cartItem['id']]);
        } else {
            // Add new item to cart
            $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([currentUserId(), $product_id, $quantity]);
        }
        // Get updated count
        $c = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) as total_items FROM cart WHERE user_id = ?');
        $c->execute([currentUserId()]);
        $count = (int)($c->fetch(PDO::FETCH_ASSOC)['total_items'] ?? 0);
        echo json_encode(['success' => true, 'message' => 'Producto añadido al carrito', 'count' => $count]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al añadir producto al carrito: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>