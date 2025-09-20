<?php
require_once '../includes/db.php';
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
$limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$category = isset($_GET['category']) ? (int)$_GET['category'] : null;

try {
    // Ensure products has tags column for older installs
    try { $pdo->exec("ALTER TABLE products ADD COLUMN tags VARCHAR(500) NULL"); } catch (Exception $ignore) {}
} catch (Exception $ignore) {}

$sql = "SELECT p.* FROM products p WHERE 1=1";
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.tags LIKE ?)";
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if (!empty($category)) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category;
}

$sql .= " ORDER BY p.created_at DESC LIMIT " . (int)$limit;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'results' => $rows], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>