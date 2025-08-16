<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

requireAdmin();

$admin_id = getCurrentAdminId($pdo);
$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de producto no proporcionado.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND admin_id = ?");
    $stmt->execute([$product_id, $admin_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        echo json_encode(['success' => true, 'data' => $product]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado o no tienes permiso para verlo.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>