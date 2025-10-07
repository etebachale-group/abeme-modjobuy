<?php
require_once 'includes/db.php';

try {
    echo "Database connection successful!\n\n";
    // Check categories
    $stmt = $pdo->prepare("SELECT * FROM categories");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Categories:\n";
    if (count($categories) > 0) {
        foreach ($categories as $category) {
            echo "- " . $category['name'] . " (ID: " . $category['id'] . ")\n";
        }
    } else {
        echo "No categories found.\n";
    }
    
    echo "\n";
    
    // Check users
    $stmt = $pdo->prepare("SELECT * FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Users:\n";
    foreach ($users as $user) {
        echo "- " . $user['email'] . " (" . $user['role'] . ")\n";
    }
    
    echo "\n";
    
    // Check admins
    $stmt = $pdo->prepare("SELECT * FROM admins");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Admins:\n";
    foreach ($admins as $admin) {
        echo "- User ID: " . $admin['user_id'] . ", Company: " . $admin['company_name'] . "\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>