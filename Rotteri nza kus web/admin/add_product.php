<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check if user is admin
requireAdmin();

// Get admin ID
$admin_id = getCurrentAdminId($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = $_POST['category_id'] ?? null;
    $price = $_POST['price'] ?? 0;
    $weight = $_POST['weight'] ?? 0;
    $image_url = $_POST['image_url'] ?? '';
    
    // Handle file upload
    $uploaded_image_url = '';
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/';
        $uploadFile = $uploadDir . basename($_FILES['image_file']['name']);
        
        // Generate unique filename to avoid conflicts
        $fileExtension = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
        $uniqueFilename = uniqid() . '.' . $fileExtension;
        $uploadFile = $uploadDir . $uniqueFilename;
        
        // Check if file is an image
        $imageFileType = strtolower(pathinfo($uploadFile, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($imageFileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadFile)) {
                $uploaded_image_url = 'uploads/' . $uniqueFilename;
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al subir la imagen.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Solo se permiten archivos JPG, JPEG, PNG y GIF.']);
            exit;
        }
    }
    
    // Use uploaded image URL if available, otherwise use provided URL
    if (!empty($uploaded_image_url)) {
        $image_url = $uploaded_image_url;
    }
    
    // Validate required fields
    if (empty($name) || empty($description) || empty($category_id) || empty($price) || empty($image_url)) {
        echo json_encode(['success' => false, 'message' => 'Por favor, rellena todos los campos obligatorios.']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO products (name, description, price, weight, image_url, category_id, admin_id) 
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$name, $description, $price, $weight, $image_url, $category_id, $admin_id]);
        
        echo json_encode(['success' => true, 'message' => 'Producto agregado exitosamente.']);
        
    } catch (PDOException $e) {
        // In a real app, you would log this error
        echo json_encode(['success' => false, 'message' => 'Error al agregar el producto: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}
?>