<?php
// This script will run the add_categories_and_products functionality through the web interface
require_once 'Rotteri nza kus web/includes/db.php';
require_once 'Rotteri nza kus web/includes/auth.php';

// Add categories if they don't exist
function addCategories($pdo) {
    $categories = [
        ['name' => 'Electrónica', 'description' => 'Dispositivos electrónicos y gadgets'],
        ['name' => 'Moda', 'description' => 'Ropa, calzado y accesorios'],
        ['name' => 'Hogar', 'description' => 'Productos para el hogar y decoración'],
        ['name' => 'Deportes', 'description' => 'Equipamiento y ropa deportiva'],
        ['name' => 'Belleza', 'description' => 'Productos de belleza y cuidado personal']
    ];
    
    foreach ($categories as $category) {
        // Check if category already exists
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->execute([$category['name']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->execute([$category['name'], $category['description']]);
            echo "<p>Added category: " . $category['name'] . "</p>";
        } else {
            echo "<p>Category already exists: " . $category['name'] . "</p>";
        }
    }
}

// Add products to the database
function addProducts($pdo, $admin_id) {
    // Get category IDs
    $stmt = $pdo->prepare("SELECT id, name FROM categories");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $categoryMap = [];
    foreach ($categories as $category) {
        $categoryMap[$category['name']] = $category['id'];
    }
    
    // Sample products data - 8 products across 5 categories
    $products = [
        // Electrónica
        [
            'name' => 'Smartphone XYZ Pro',
            'description' => 'Smartphone de última generación con pantalla OLED de 6.7 pulgadas, cámara triple de 108MP, batería de 5000mAh y carga rápida de 65W.',
            'price' => 299990,
            'weight' => 0.22,
            'image_url' => 'https://example.com/images/smartphone.jpg',
            'category_id' => $categoryMap['Electrónica'],
            'stock_quantity' => 50
        ],
        [
            'name' => 'Laptop UltraSlim',
            'description' => 'Laptop ultradelgada con procesador Intel Core i7, 16GB RAM, 512GB SSD, pantalla de 14 pulgadas y batería de 10 horas.',
            'price' => 799990,
            'weight' => 1.3,
            'image_url' => 'https://example.com/images/laptop.jpg',
            'category_id' => $categoryMap['Electrónica'],
            'stock_quantity' => 25
        ],
        [
            'name' => 'Auriculares Inalámbricos',
            'description' => 'Auriculares Bluetooth con cancelación de ruido, 30 horas de batería, micrófono integrado y estuche de carga.',
            'price' => 89990,
            'weight' => 0.3,
            'image_url' => 'https://example.com/images/headphones.jpg',
            'category_id' => $categoryMap['Electrónica'],
            'stock_quantity' => 100
        ],
        [
            'name' => 'Smartwatch Fitness',
            'description' => 'Reloj inteligente con monitor de frecuencia cardíaca, GPS, resistente al agua y hasta 7 días de batería.',
            'price' => 149990,
            'weight' => 0.15,
            'image_url' => 'https://example.com/images/smartwatch.jpg',
            'category_id' => $categoryMap['Electrónica'],
            'stock_quantity' => 75
        ],
        [
            'name' => 'Tablet 10 Pulgadas',
            'description' => 'Tablet con pantalla de 10 pulgadas, 64GB de almacenamiento, cámara dual y batería de 7000mAh.',
            'price' => 249990,
            'weight' => 0.48,
            'image_url' => 'https://example.com/images/tablet.jpg',
            'category_id' => $categoryMap['Electrónica'],
            'stock_quantity' => 40
        ],
        
        // Moda
        [
            'name' => 'Camiseta Premium',
            'description' => 'Camiseta de algodón orgánico de alta calidad, disponible en varios colores y tallas, corte moderno y ajuste perfecto.',
            'price' => 19990,
            'weight' => 0.2,
            'image_url' => 'https://example.com/images/tshirt.jpg',
            'category_id' => $categoryMap['Moda'],
            'stock_quantity' => 200
        ],
        [
            'name' => 'Jeans Slim Fit',
            'description' => 'Jeans de corte slim con elastano para mayor comodidad, lavado oscuro y detalles modernos.',
            'price' => 49990,
            'weight' => 0.8,
            'image_url' => 'https://example.com/images/jeans.jpg',
            'category_id' => $categoryMap['Moda'],
            'stock_quantity' => 150
        ],
        [
            'name' => 'Zapatos Deportivos',
            'description' => 'Zapatos deportivos con suela antideslizante, amortiguación avanzada y materiales transpirables.',
            'price' => 79990,
            'weight' => 0.6,
            'image_url' => 'https://example.com/images/sneakers.jpg',
            'category_id' => $categoryMap['Moda'],
            'stock_quantity' => 80
        ],
        [
            'name' => 'Chaqueta de Cuero',
            'description' => 'Chaqueta de cuero genuino con forro térmico, cierre de cremallera y múltiples bolsillos.',
            'price' => 199990,
            'weight' => 1.2,
            'image_url' => 'https://example.com/images/leather-jacket.jpg',
            'category_id' => $categoryMap['Moda'],
            'stock_quantity' => 30
        ],
        [
            'name' => 'Bolso de Mano',
            'description' => 'Bolso de mano elegante con múltiples compartimentos, asa superior y correa ajustable.',
            'price' => 89990,
            'weight' => 0.5,
            'image_url' => 'https://example.com/images/handbag.jpg',
            'category_id' => $categoryMap['Moda'],
            'stock_quantity' => 60
        ],
        
        // Hogar
        [
            'name' => 'Juego de Sartenes Antiadherentes',
            'description' => 'Set de 5 sartenes antiadherentes con revestimiento de cerámica, mango ergonómico y compatible con inducción.',
            'price' => 39990,
            'weight' => 2.5,
            'image_url' => 'https://example.com/images/pan-set.jpg',
            'category_id' => $categoryMap['Hogar'],
            'stock_quantity' => 45
        ],
        [
            'name' => 'Robot Aspirador Inteligente',
            'description' => 'Robot aspirador con navegación láser, control por app, filtro HEPA y hasta 120 minutos de autonomía.',
            'price' => 299990,
            'weight' => 3.2,
            'image_url' => 'https://example.com/images/robot-vacuum.jpg',
            'category_id' => $categoryMap['Hogar'],
            'stock_quantity' => 20
        ],
        [
            'name' => 'Set de Toallas de Baño',
            'description' => 'Set de 6 toallas de baño de algodón egipcio, suaves y absorbentes, disponibles en varios colores.',
            'price' => 24990,
            'weight' => 1.8,
            'image_url' => 'https://example.com/images/towel-set.jpg',
            'category_id' => $categoryMap['Hogar'],
            'stock_quantity' => 90
        ],
        [
            'name' => 'Lámpara LED Inteligente',
            'description' => 'Lámpara LED con control de brillo y color, compatible con asistentes de voz y app móvil.',
            'price' => 49990,
            'weight' => 0.8,
            'image_url' => 'https://example.com/images/led-lamp.jpg',
            'category_id' => $categoryMap['Hogar'],
            'stock_quantity' => 70
        ],
        [
            'name' => 'Set de Cubiertos de Acero Inoxidable',
            'description' => 'Set de 24 piezas de cubiertos de acero inoxidable 18/10, con estuche de madera y diseño clásico.',
            'price' => 34990,
            'weight' => 1.2,
            'image_url' => 'https://example.com/images/cutlery-set.jpg',
            'category_id' => $categoryMap['Hogar'],
            'stock_quantity' => 55
        ],
        
        // Deportes
        [
            'name' => 'Bicicleta de Montaña',
            'description' => 'Bicicleta de montaña con marco de aluminio, 21 velocidades, frenos de disco y neumáticos todo terreno.',
            'price' => 399990,
            'weight' => 12.5,
            'image_url' => 'https://example.com/images/mountain-bike.jpg',
            'category_id' => $categoryMap['Deportes'],
            'stock_quantity' => 15
        ],
        [
            'name' => 'Pesas Ajustables',
            'description' => 'Set de pesas ajustables de 20kg con base antideslizante, mango ergonómico y sistema de ajuste rápido.',
            'price' => 149990,
            'weight' => 5.0,
            'image_url' => 'https://example.com/images/adjustable-weights.jpg',
            'category_id' => $categoryMap['Deportes'],
            'stock_quantity' => 35
        ],
        [
            'name' => 'Yoga Mat Profesional',
            'description' => 'Colchoneta de yoga de doble capa con superficie antideslizante, material ecológico y diseño ergonómico.',
            'price' => 29990,
            'weight' => 0.9,
            'image_url' => 'https://example.com/images/yoga-mat.jpg',
            'category_id' => $categoryMap['Deportes'],
            'stock_quantity' => 120
        ],
        [
            'name' => 'Pelota de Fútbol Profesional',
            'description' => 'Pelota de fútbol tamaño oficial con costuras reforzadas, cámara butilo y diseño FIFA aprobado.',
            'price' => 19990,
            'weight' => 0.45,
            'image_url' => 'https://example.com/images/football.jpg',
            'category_id' => $categoryMap['Deportes'],
            'stock_quantity' => 85
        ],
        [
            'name' => 'Reloj Deportivo GPS',
            'description' => 'Reloj deportivo con GPS integrado, monitor de frecuencia cardíaca, resistente al agua y hasta 14 días de batería.',
            'price' => 129990,
            'weight' => 0.18,
            'image_url' => 'https://example.com/images/sports-watch.jpg',
            'category_id' => $categoryMap['Deportes'],
            'stock_quantity' => 40
        ],
        
        // Belleza
        [
            'name' => 'Set de Maquillaje Profesional',
            'description' => 'Set completo de maquillaje con 50 piezas, incluyendo sombras, delineadores, rubores y pinceles de alta calidad.',
            'price' => 59990,
            'weight' => 1.0,
            'image_url' => 'https://example.com/images/makeup-set.jpg',
            'category_id' => $categoryMap['Belleza'],
            'stock_quantity' => 65
        ],
        [
            'name' => 'Crema Hidratante Facial',
            'description' => 'Crema hidratante facial con SPF 30, ingredientes naturales, no comedogénica y apta para todo tipo de piel.',
            'price' => 14990,
            'weight' => 0.05,
            'image_url' => 'https://example.com/images/face-cream.jpg',
            'category_id' => $categoryMap['Belleza'],
            'stock_quantity' => 200
        ],
        [
            'name' => 'Secador de Pelo Profesional',
            'description' => 'Secador de pelo con 2000W de potencia, 3 velocidades, tecnología iónica y difusor incluido.',
            'price' => 39990,
            'weight' => 0.8,
            'image_url' => 'https://example.com/images/hair-dryer.jpg',
            'category_id' => $categoryMap['Belleza'],
            'stock_quantity' => 50
        ],
        [
            'name' => 'Perfume Masculino Elegance',
            'description' => 'Perfume masculino con notas de bergamota, vetiver y almizcle, frasco de cristal con diseño elegante.',
            'price' => 29990,
            'weight' => 0.1,
            'image_url' => 'https://example.com/images/mens-perfume.jpg',
            'category_id' => $categoryMap['Belleza'],
            'stock_quantity' => 95
        ],
        [
            'name' => 'Set de Cuidado Capilar',
            'description' => 'Set de 3 productos para el cuidado del cabello: champú, acondicionador y mascarilla reparadora.',
            'price' => 19990,
            'weight' => 0.9,
            'image_url' => 'https://example.com/images/hair-care-set.jpg',
            'category_id' => $categoryMap['Belleza'],
            'stock_quantity' => 110
        ]
    ];
    
    // Add products to database
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
                $admin_id,
                $product['stock_quantity']
            ]);
            echo "<p>Added product: " . $product['name'] . "</p>";
            $addedCount++;
        } catch (PDOException $e) {
            echo "<p>Error adding product " . $product['name'] . ": " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<p>Total products added: " . $addedCount . "</p>";
}

// Get admin ID
function getAdminId($pdo) {
    $stmt = $pdo->prepare("SELECT id FROM admins LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        return $admin['id'];
    } else {
        // Create admin if doesn't exist
        $stmt = $pdo->prepare("INSERT INTO admins (user_id, company_name) VALUES (1, 'Rotteri Nza Kus Admin')");
        $stmt->execute();
        return $pdo->lastInsertId();
    }
}

// Run the script
echo "<h1>Adding Categories and Products</h1>";

try {
    echo "<p>Starting to add categories and products...</p>";
    
    // Add categories
    addCategories($pdo);
    
    // Get admin ID
    $admin_id = getAdminId($pdo);
    echo "<p>Using admin ID: " . $admin_id . "</p>";
    
    // Add products
    addProducts($pdo, $admin_id);
    
    echo "<p>Finished adding categories and products.</p>";
} catch (PDOException $e) {
    echo "<p>Database error: " . $e->getMessage() . "</p>";
}
?>