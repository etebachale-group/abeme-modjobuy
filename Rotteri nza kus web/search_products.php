<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

// Get search term from GET parameter
$searchTerm = $_GET['q'] ?? '';

if (empty($searchTerm)) {
    echo json_encode([]);
    exit;
}

try {
    // Search for products with similar names
    $stmt = $pdo->prepare("SELECT id, name FROM products WHERE name LIKE ? AND is_active = 1 ORDER BY name LIMIT 10");
    $stmt->execute(["%$searchTerm%"]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($products);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>