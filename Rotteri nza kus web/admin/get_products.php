<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

requireAdmin();

$admin_id = getCurrentAdminId($pdo);

try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE admin_id = ? ORDER BY created_at DESC");
    $stmt->execute([$admin_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'products' => $products]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>