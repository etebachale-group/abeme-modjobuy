<?php
require_once 'includes/db.php';

try {
    // Test the connection
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    
    echo "Database connection successful! Found {$count} users in the database.";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>