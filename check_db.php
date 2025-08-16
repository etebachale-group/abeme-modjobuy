<?php
require_once 'Rotteri nza kus web/includes/db.php';

try {
    // Check categories
    $stmt = $pdo->prepare("SELECT * FROM categories");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Categories:\n";
    foreach ($categories as $category) {
        echo "- " . $category['name'] . " (ID: " . $category['id'] . ")\n";
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