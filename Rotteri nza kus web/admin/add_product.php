<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check if user is admin (API style responses)
requireAdminApi();

// Get admin ID
$admin_id = getCurrentAdminId($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        exit;
    }
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = $_POST['category_id'] ?? null;
    $price = $_POST['price'] ?? 0;
    $weight = $_POST['weight'] ?? 0;
    $tags = trim($_POST['tags'] ?? '');
    $stock = $_POST['stock'] ?? null;
    $source_url = trim($_POST['source_url'] ?? '');
    $image_url = $_POST['image_url'] ?? '';
    
    // Handle file upload
    $uploaded_image_url = '';
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Error de carga (' . (int)$_FILES['image_file']['error'] . ').']);
            exit;
        }
        $uploadDir = realpath(__DIR__ . '/../uploads');
        if ($uploadDir === false) {
            // try to create
            $target = __DIR__ . '/../uploads';
            if (!@mkdir($target, 0775, true) && !is_dir($target)) {
                echo json_encode(['success' => false, 'message' => 'No se pudo crear el directorio de subidas.']);
                exit;
            }
            $uploadDir = realpath($target);
        }
        $fileExtension = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($fileExtension, $allowedTypes, true)) {
            echo json_encode(['success' => false, 'message' => 'Formato no permitido.']);
            exit;
        }
        // Validate MIME
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['image_file']['tmp_name']);
        $allowedMimes = ['image/jpeg','image/png','image/gif','image/webp'];
        if (!in_array($mime, $allowedMimes, true)) {
            echo json_encode(['success' => false, 'message' => 'Contenido de archivo inválido.']);
            exit;
        }
        $uniqueFilename = bin2hex(random_bytes(8)) . '.' . $fileExtension;
        $dest = $uploadDir . DIRECTORY_SEPARATOR . $uniqueFilename;
        if (!move_uploaded_file($_FILES['image_file']['tmp_name'], $dest)) {
            echo json_encode(['success' => false, 'message' => 'No se pudo mover el archivo.']);
            exit;
        }
        $uploaded_image_url = 'uploads/' . $uniqueFilename;
    }
    
    // Use uploaded image URL if available, otherwise use provided URL
    if (!empty($uploaded_image_url)) {
        $image_url = $uploaded_image_url;
    }
    
    // Validate required fields
    if (empty($name) || empty($description) || empty($category_id) || empty($price) || (empty($image_url) && empty($uploaded_image_url))) {
        echo json_encode(['success' => false, 'message' => 'Por favor, rellena todos los campos obligatorios.']);
        exit;
    }
    
    try {
        // Ensure source_url exists for legacy schemas
        try { $pdo->exec("ALTER TABLE products ADD COLUMN source_url VARCHAR(500) NULL"); } catch (Exception $ignore) {}
        // Ensure stock column exists (nullable means no stock control)
        try { $pdo->exec("ALTER TABLE products ADD COLUMN stock INT NULL"); } catch (Exception $ignore) {}
        // Normalize stock: empty string -> NULL
        $stockVal = (isset($stock) && $stock !== '' && $stock !== null) ? max(0, (int)$stock) : null;
        $stmt = $pdo->prepare(
            "INSERT INTO products (name, description, price, weight, image_url, category_id, admin_id, tags, source_url, stock) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$name, $description, $price, $weight, $image_url, $category_id, $admin_id, $tags, $source_url, $stockVal]);
        
    $newId = $pdo->lastInsertId();
    echo json_encode(['success' => true, 'message' => 'Producto agregado exitosamente.', 'id' => $newId, 'image_url' => $image_url]);
        
    } catch (PDOException $e) {
        // In a real app, you would log this error
        echo json_encode(['success' => false, 'message' => 'Error al agregar el producto: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}
?>