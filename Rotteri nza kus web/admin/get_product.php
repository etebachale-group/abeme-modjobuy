<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user is admin
requireAdmin();

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $product_id = $_GET['id'];
    $admin_id = getCurrentAdminId($pdo);
    
    try {
        // Check if product belongs to this admin
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND admin_id = ?");
        $stmt->execute([$product_id, $admin_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            echo json_encode(['success' => true, 'product' => $product]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado o no tienes permiso para editarlo']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener el producto: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'ID de producto no proporcionado']);
}
?>