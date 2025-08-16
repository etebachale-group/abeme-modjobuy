<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check if user is admin
requireAdmin();

// Get admin ID
$admin_id = getCurrentAdminId($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? null;

    if (empty($product_id)) {
        echo json_encode(['success' => false, 'message' => 'ID de producto no proporcionado.']);
        exit;
    }

    try {
        // First, verify the product belongs to the admin
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND admin_id = ?");
        $stmt->execute([$product_id, $admin_id]);
        $product = $stmt->fetch();

        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado o no tienes permiso para eliminarlo.']);
            exit;
        }

        // If it exists and belongs to the admin, delete it
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$product_id]);

        echo json_encode(['success' => true, 'message' => 'Producto eliminado exitosamente.']);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar el producto: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}
?>