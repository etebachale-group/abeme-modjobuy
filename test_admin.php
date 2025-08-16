<?php
require_once 'Rotteri nza kus web/includes/db.php';

try {
    // Test fetching products
    $stmt = $pdo->prepare("SELECT * FROM products LIMIT 5");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "First 5 products:\n";
    foreach ($products as $product) {
        echo "- {$product['name']} (CFA {$product['price']})\n";
    }
    
    // Test fetching categories
    $stmt = $pdo->prepare("SELECT * FROM categories");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nCategories:\n";
    foreach ($categories as $category) {
        echo "- {$category['name']}\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>