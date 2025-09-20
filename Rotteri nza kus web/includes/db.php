<?php
// Wrapper to reuse the primary application's DB connection (shared users/admins)
require_once __DIR__ . '/../../includes/db.php';

// Optional: ensure extended user columns exist if frontend expects them
try {
    // Attempt to add columns only if missing (MySQL 8+ supports IF NOT EXISTS for ADD COLUMN; fallback silently)
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS first_name VARCHAR(100) NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_name VARCHAR(100) NULL");
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(50) NOT NULL DEFAULT 'user'");
} catch (Exception $e) {
    // Ignore if DB version doesn't support IF NOT EXISTS or lacks privileges
}

// Ensure minimal cart table exists for storefront functionality
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cart (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id),
        INDEX(product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // best effort; APIs will surface errors if this fails
}

// Ensure notifications table exists for user order status updates
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(150) NOT NULL,
        message TEXT NULL,
        link VARCHAR(255) NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id),
        INDEX(is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // ignore
}
?>