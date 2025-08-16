<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

requireAdmin();

$admin_id = getCurrentAdminId($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? null;
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = $_POST['category_id'] ?? null;
    $price = $_POST['price'] ?? 0;
    $weight = $_POST['weight'] ?? 0;
    $image_url = $_POST['image_url'] ?? '';

    if (empty($product_id) || empty($name) || empty($description) || empty($category_id) || empty($price) || empty($image_url)) {
        echo json_encode(['success' => false, 'message' => 'Por favor, rellena todos los campos obligatorios.']);
        exit;
    }

    try {
        // Verify product belongs to admin before updating
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND admin_id = ?");
        $stmt->execute([$product_id, $admin_id]);
        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Producto no encontrado o no tienes permiso para editarlo.']);
            exit;
        }

        $stmt = $pdo->prepare(
            "UPDATE products 
             SET name = ?, description = ?, price = ?, weight = ?, image_url = ?, category_id = ?
             WHERE id = ?"
        );
        $stmt->execute([$name, $description, $price, $weight, $image_url, $category_id, $product_id]);

        echo json_encode(['success' => true, 'message' => 'Producto actualizado exitosamente.']);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el producto: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}
?>