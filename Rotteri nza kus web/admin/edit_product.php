<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user is admin
requireAdmin();

// Get admin ID
$admin_id = getCurrentAdminId($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = $_POST['product_id'] ?? '';
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = $_POST['category_id'] ?? '';
    $price = $_POST['price'] ?? '';
    $weight = $_POST['weight'] ?? '';
    $image_url = $_POST['image_url'] ?? '';
    $stock = $_POST['stock'] ?? null;
    
    // Validate inputs
    $errors = [];
    if (empty($product_id)) $errors[] = 'ID de producto es obligatorio';
    if (empty($name)) $errors[] = 'El nombre del producto es obligatorio';
    if (empty($description)) $errors[] = 'La descripción del producto es obligatoria';
    if (empty($category_id)) $errors[] = 'La categoría del producto es obligatoria';
    if (empty($price) || !is_numeric($price)) $errors[] = 'El precio del producto es obligatorio y debe ser numérico';
    if (empty($weight) || !is_numeric($weight)) $errors[] = 'El peso del producto es obligatorio y debe ser numérico';
    if (empty($image_url)) $errors[] = 'La URL de la imagen del producto es obligatoria';
    
    if (empty($errors)) {
        try {
            // Check if product belongs to this admin
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND admin_id = ?");
            $stmt->execute([$product_id, $admin_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Producto no encontrado o no tienes permiso para editarlo']);
                exit;
            }
            
            // Ensure stock column exists
            try { $pdo->exec("ALTER TABLE products ADD COLUMN stock INT NULL"); } catch (Exception $ignore) {}
            // Normalize stock
            $stockVal = (isset($stock) && $stock !== '' && $stock !== null) ? max(0, (int)$stock) : null;
            // Update product including stock
            $stmt = $pdo->prepare("UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, weight = ?, image_url = ?, stock = ? WHERE id = ?");
            $stmt->execute([$category_id, $name, $description, $price, $weight, $image_url, $stockVal, $product_id]);
            
            // Log the action
            $stmt = $pdo->prepare("INSERT INTO product_history (product_id, admin_id, change_type, field_name, new_value) VALUES (?, ?, 'updated', 'product', ?)");
            $stmt->execute([$product_id, $admin_id, $name]);
            
            echo json_encode(['success' => true, 'message' => 'Producto actualizado exitosamente']);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar el producto: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>