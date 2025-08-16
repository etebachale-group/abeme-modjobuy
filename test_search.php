<?php
require_once 'Rotteri nza kus web/includes/db.php';

try {
    // Test search for products with similar names
    $searchTerm = 'Smartphone';
    $stmt = $pdo->prepare("SELECT id, name FROM products WHERE name LIKE ? AND is_active = 1 ORDER BY name LIMIT 10");
    $stmt->execute(["%$searchTerm%"]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Search results for '$searchTerm':\n";
    foreach ($products as $product) {
        echo "- {$product['name']}\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>