<?php
require_once 'Rotteri nza kus web/includes/db.php';

try {
    // Insert categories
    $categories = [
        ['name' => 'Electrónica', 'description' => 'Dispositivos electrónicos y gadgets'],
        ['name' => 'Moda', 'description' => 'Ropa, calzado y accesorios'],
        ['name' => 'Hogar', 'description' => 'Artículos para el hogar y decoración'],
        ['name' => 'Deportes', 'description' => 'Equipamiento y ropa deportiva']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
    foreach ($categories as $category) {
        $stmt->execute([$category['name'], $category['description']]);
    }
    echo "Categories inserted successfully.\n";
    
    // Get admin ID
    $stmt = $pdo->prepare("SELECT id FROM admins LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    $admin_id = $admin['id'];
    
    // Insert products
    $products = [
        // Electrónica
        [
            'admin_id' => $admin_id,
            'category_id' => 1,
            'name' => 'Smartphone Android',
            'description' => 'Smartphone de última generación con pantalla HD y cámara de alta resolución',
            'price' => 250000,
            'weight' => 0.2,
            'image_url' => 'https://images.unsplash.com/photo-1595941069915-4ebc5197c14a?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
        ],
        [
            'admin_id' => $admin_id,
            'category_id' => 1,
            'name' => 'Laptop',
            'description' => 'Laptop de alto rendimiento con procesador de última generación',
            'price' => 450000,
            'weight' => 1.5,
            'image_url' => 'https://images.unsplash.com/photo-1496181133067-bf336fc80e38?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
        ],
        [
            'admin_id' => $admin_id,
            'category_id' => 1,
            'name' => 'Auriculares Inalámbricos',
            'description' => 'Auriculares con cancelación de ruido y sonido de alta calidad',
            'price' => 85000,
            'weight' => 0.3,
            'image_url' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d318?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
        ],
        // Moda
        [
            'admin_id' => $admin_id,
            'category_id' => 2,
            'name' => 'Camisa de Vestir',
            'description' => 'Camisa de vestir de algodón de alta calidad',
            'price' => 35000,
            'weight' => 0.5,
            'image_url' => 'https://images.unsplash.com/photo-1521334884684-d80222895326?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
        ],
        [
            'admin_id' => $admin_id,
            'category_id' => 2,
            'name' => 'Zapatos de Cuero',
            'description' => 'Zapatos de cuero genuino para hombre',
            'price' => 75000,
            'weight' => 1.2,
            'image_url' => 'https://images.unsplash.com/photo-1549298916-b41d501d3772?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
        ],
        [
            'admin_id' => $admin_id,
            'category_id' => 2,
            'name' => 'Bolso de Diseñador',
            'description' => 'Bolso de diseñador con múltiples compartimentos',
            'price' => 120000,
            'weight' => 0.8,
            'image_url' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
        ],
        // Hogar
        [
            'admin_id' => $admin_id,
            'category_id' => 3,
            'name' => 'Juego de Sartenes',
            'description' => 'Juego de sartenes antiadherentes de 5 piezas',
            'price' => 65000,
            'weight' => 3.0,
            'image_url' => 'https://images.unsplash.com/photo-1583255448430-15d12784bfaa?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
        ],
        [
            'admin_id' => $admin_id,
            'category_id' => 3,
            'name' => 'Set de Toallas',
            'description' => 'Set de 4 toallas de baño de algodón egipcio',
            'price' => 45000,
            'weight' => 2.0,
            'image_url' => 'https://images.unsplash.com/photo-1566073701805-98da02309644?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
        ],
        [
            'admin_id' => $admin_id,
            'category_id' => 3,
            'name' => 'Lámpara de Escritorio',
            'description' => 'Lámpara LED ajustable para escritorio',
            'price' => 30000,
            'weight' => 1.0,
            'image_url' => 'https://images.unsplash.com/photo-1561516666-9f87a2e4f7e2?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
        ],
        // Deportes
        [
            'admin_id' => $admin_id,
            'category_id' => 4,
            'name' => 'Zapatillas Deportivas',
            'description' => 'Zapatillas deportivas para running con amortiguación avanzada',
            'price' => 95000,
            'weight' => 1.1,
            'image_url' => 'https://images.unsplash.com/photo-1542280716-50f20c084521?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
        ],
        [
            'admin_id' => $admin_id,
            'category_id' => 4,
            'name' => 'Mochila Deportiva',
            'description' => 'Mochila deportiva resistente al agua con múltiples compartimentos',
            'price' => 55000,
            'weight' => 0.7,
            'image_url' => 'https://images.unsplash.com/photo-1581362158731-7a833e1e48e6?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
        ],
        [
            'admin_id' => $admin_id,
            'category_id' => 4,
            'name' => 'Botella de Agua',
            'description' => 'Botella de agua de acero inoxidable con aislamiento térmico',
            'price' => 25000,
            'weight' => 0.4,
            'image_url' => 'https://images.unsplash.com/photo-1602143407151-7111542de6e2?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
        ],
        // Productos adicionales para llegar a 15
        [
            'admin_id' => $admin_id,
            'category_id' => 1,
            'name' => 'Tablet',
            'description' => 'Tablet de 10 pulgadas con pantalla retina',
            'price' => 200000,
            'weight' => 0.5,
            'image_url' => 'https://images.unsplash.com/photo-1565932887479-b18108f07ffd?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
        ],
        [
            'admin_id' => $admin_id,
            'category_id' => 2,
            'name' => 'Reloj de Pulsera',
            'description' => 'Reloj de pulsera analógico con correa de cuero',
            'price' => 80000,
            'weight' => 0.3,
            'image_url' => 'https://images.unsplash.com/photo-1523275335684-3cd7d0b50d7b?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
        ],
        [
            'admin_id' => $admin_id,
            'category_id' => 3,
            'name' => 'Cortinas',
            'description' => 'Cortinas blackout para sala de estar',
            'price' => 40000,
            'weight' => 1.5,
            'image_url' => 'https://images.unsplash.com/photo-1591703290051-f7f3911d1d8f?ixlib=rb-1.2.1&auto=format&fit=crop&w=500&q=80'
        ]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO products (admin_id, category_id, name, description, price, weight, image_url) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($products as $product) {
        $stmt->execute([
            $product['admin_id'],
            $product['category_id'],
            $product['name'],
            $product['description'],
            $product['price'],
            $product['weight'],
            $product['image_url']
        ]);
    }
    echo "Products inserted successfully.\n";
    
    echo "Database populated with initial data successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>