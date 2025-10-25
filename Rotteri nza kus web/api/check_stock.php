<?php
require_once '../includes/db.php';

header('Content-Type: application/json');

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'product_id requerido']);
    exit;
}

try {
    // Ensure stock column exists (nullable means no control)
    try { $pdo->exec("ALTER TABLE products ADD COLUMN stock INT NULL"); } catch (Exception $e) {}

    $stmt = $pdo->prepare("SELECT COALESCE(stock, -1) as stock FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
        exit;
    }
    $stock = (int)$row['stock'];
    // stock = -1 => sin control
    echo json_encode(['success' => true, 'managed' => $stock >= 0, 'stock' => $stock >= 0 ? $stock : null]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
