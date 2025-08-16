<?php
// Script to add additional admins and products for those admins

require_once 'Rotteri nza kus web/includes/db.php';

// Function to create a new admin user
function createAdminUser($pdo, $email, $firstName, $lastName, $companyName) {
    try {
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "User with email {$email} already exists.\n";
            return $user['id'];
        }
        
        // Create user
        $password = password_hash('password123', PASSWORD_DEFAULT); // Default password
        $username = $email;
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, first_name, last_name, role) VALUES (?, ?, ?, ?, ?, 'admin')");
        $stmt->execute([$username, $email, $password, $firstName, $lastName, 'admin']);
        
        $userId = $pdo->lastInsertId();
        echo "Created user: {$firstName} {$lastName} (ID: {$userId})\n";
        
        // Create admin record
        $stmt = $pdo->prepare("INSERT INTO admins (user_id, company_name) VALUES (?, ?)");
        $stmt->execute([$userId, $companyName]);
        
        $adminId = $pdo->lastInsertId();
        echo "Created admin: {$companyName} (ID: {$adminId})\n";
        
        return $userId;
    } catch (PDOException $e) {
        echo "Error creating admin user {$email}: " . $e->getMessage() . "\n";
        return null;
    }
}

// Function to add products for an admin
function addProductsForAdmin($pdo, $adminId, $categoryMap) {
    // Sample products for this admin
    $products = [
        [
            'name' => 'Smartphone Pro Max',
            'description' => 'Latest smartphone with advanced features, 128GB storage, dual camera system',
            'price' => 499990,
            'weight' => 0.2,
            'image_url' => 'https://example.com/images/smartphone-pro.jpg',
            'category_id' => $categoryMap['Electrónica'],
            'stock_quantity' => 30
        ],
        [
            'name' => 'Designer Jeans',
            'description' => 'Premium quality jeans with perfect fit, available in multiple sizes',
            'price' => 79990,
            'weight' => 0.8,
            'image_url' => 'https://example.com/images/designer-jeans.jpg',
            'category_id' => $categoryMap['Moda'],
            'stock_quantity' => 100
        ],
        [
            'name' => 'Kitchen Blender',
            'description' => 'Powerful blender with multiple speed settings, easy to clean',
            'price' => 39990,
            'weight' => 1.5,
            'image_url' => 'https://example.com/images/kitchen-blender.jpg',
            'category_id' => $categoryMap['Hogar'],
            'stock_quantity' => 50
        ],
        [
            'name' => 'Running Shoes',
            'description' => 'Comfortable running shoes with extra cushioning, perfect for daily exercise',
            'price' => 129990,
            'weight' => 0.9,
            'image_url' => 'https://example.com/images/running-shoes.jpg',
            'category_id' => $categoryMap['Deportes'],
            'stock_quantity' => 75
        ],
        [
            'name' => 'Skincare Set',
            'description' => 'Complete skincare set with cleanser, toner, and moisturizer',
            'price' => 59990,
            'weight' => 0.5,
            'image_url' => 'https://example.com/images/skincare-set.jpg',
            'category_id' => $categoryMap['Belleza'],
            'stock_quantity' => 40
        ]
    ];
    
    $addedCount = 0;
    foreach ($products as $product) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO products (name, description, price, weight, image_url, category_id, admin_id, stock_quantity) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $product['name'],
                $product['description'],
                $product['price'],
                $product['weight'],
                $product['image_url'],
                $product['category_id'],
                $adminId,
                $product['stock_quantity']
            ]);
            echo "Added product: " . $product['name'] . " for admin ID: {$adminId}\n";
            $addedCount++;
        } catch (PDOException $e) {
            echo "Error adding product " . $product['name'] . ": " . $e->getMessage() . "\n";
        }
    }
    
    echo "Total products added for admin ID {$adminId}: {$addedCount}\n";
}

try {
    echo "Starting to add admins and products...\n";
    
    // Get category IDs
    $stmt = $pdo->prepare("SELECT id, name FROM categories");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $categoryMap = [];
    foreach ($categories as $category) {
        $categoryMap[$category['name']] = $category['id'];
    }
    
    // Create additional admins
    $admins = [
        [
            'email' => 'techstore@rotteri.com',
            'firstName' => 'Tech',
            'lastName' => 'Store',
            'companyName' => 'Tech Store GH'
        ],
        [
            'email' => 'fashionhub@rotteri.com',
            'firstName' => 'Fashion',
            'lastName' => 'Hub',
            'companyName' => 'Fashion Hub GQ'
        ],
        [
            'email' => 'homegoods@rotteri.com',
            'firstName' => 'Home',
            'lastName' => 'Goods',
            'companyName' => 'Home Goods GH'
        ]
    ];
    
    $adminIds = [];
    foreach ($admins as $admin) {
        $userId = createAdminUser($pdo, $admin['email'], $admin['firstName'], $admin['lastName'], $admin['companyName']);
        if ($userId) {
            $adminIds[] = $userId;
        }
    }
    
    // Add products for each admin
    foreach ($adminIds as $adminId) {
        addProductsForAdmin($pdo, $adminId, $categoryMap);
    }
    
    echo "Finished adding admins and products.\n";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>