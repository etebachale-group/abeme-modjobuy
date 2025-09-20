<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

requireAdminApi();

$admin_id = getCurrentAdminId($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}
// CSRF check
if (!csrf_validate($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$id = (int)($_POST['product_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
$price = (float)($_POST['price'] ?? 0);
$weight = (float)($_POST['weight'] ?? 0);
$image_url = trim($_POST['image_url'] ?? '');
$tags = trim($_POST['tags'] ?? '');
$source_url = trim($_POST['source_url'] ?? '');

if ($id <= 0 || $name === '' || $description === '') {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
    exit;
}

try {
    // Ensure tags/source_url columns exists for older schemas
    try { $pdo->exec("ALTER TABLE products ADD COLUMN tags VARCHAR(500) NULL"); } catch (Exception $ignore) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN source_url VARCHAR(500) NULL"); } catch (Exception $ignore) {}

    // Load current product and verify ownership
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product || (!empty($product['admin_id']) && (int)$product['admin_id'] !== (int)$admin_id)) {
        echo json_encode(['success' => false, 'message' => 'Producto no encontrado o no autorizado.']);
        exit;
    }

    // Handle optional file upload
    $uploaded_image_url = '';
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Error de carga (' . (int)$_FILES['image_file']['error'] . ').']);
            exit;
        }
        $uploadDir = realpath(__DIR__ . '/../uploads');
        if ($uploadDir === false) {
            $target = __DIR__ . '/../uploads';
            if (!@mkdir($target, 0775, true) && !is_dir($target)) {
                echo json_encode(['success' => false, 'message' => 'No se pudo crear el directorio de subidas.']);
                exit;
            }
            $uploadDir = realpath($target);
        }
        $fileExtension = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
        $allowedTypes = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($fileExtension, $allowedTypes, true)) {
            echo json_encode(['success' => false, 'message' => 'Formato no permitido.']);
            exit;
        }
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

    // Choose final image url
    $final_image_url = $uploaded_image_url !== '' ? $uploaded_image_url : ($image_url !== '' ? $image_url : (string)($product['image_url'] ?? ''));

    // If image is changing, and old image was a local upload, delete old file (best-effort)
    $old = (string)($product['image_url'] ?? '');
    if ($old !== '' && $final_image_url !== $old && strpos($old, 'uploads/') === 0) {
        $oldFull = realpath(__DIR__ . '/../' . $old);
        $uploadsDir = realpath(__DIR__ . '/../uploads');
        if ($oldFull && $uploadsDir && strpos($oldFull, $uploadsDir) === 0 && file_exists($oldFull)) {
            @unlink($oldFull);
        }
    }

    $stmt = $pdo->prepare('UPDATE products SET name=?, description=?, price=?, weight=?, image_url=?, category_id = ?, tags=?, source_url=? WHERE id = ?');
    $stmt->execute([$name, $description, $price, $weight, $final_image_url, $category_id, $tags, $source_url, $id]);

    echo json_encode(['success' => true, 'image_url' => $final_image_url]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el producto: ' . $e->getMessage()]);
}
?>