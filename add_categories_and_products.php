<?php
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
            echo "Added category: " . $category['name'] . "\n";
        } else {
            echo "Category already exists: " . $category['name'] . "\n";
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
    
    // Sample products data - 40 products across 5 categories
    $products = [
        // Electrónica (8 products)
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
        [
            'name' => 'Altavoz Bluetooth Portátil',
            'description' => 'Altavoz Bluetooth portátil con sonido 360°, batería de 12 horas y resistente al agua IPX7.',
            'price' => 39990,
            'weight' => 0.6,
            'image_url' => 'https://example.com/images/bluetooth-speaker.jpg',
            'category_id' => $categoryMap['Electrónica'],
            'stock_quantity' => 60
        ],
        [
            'name' => 'Cargador Inalámbrico Rápido',
            'description' => 'Cargador inalámbrico con carga rápida de 15W, compatible con Qi, diseño minimalista.',
            'price' => 19990,
            'weight' => 0.2,
            'image_url' => 'https://example.com/images/wireless-charger.jpg',
            'category_id' => $categoryMap['Electrónica'],
            'stock_quantity' => 150
        ],
        [
            'name' => 'Power Bank 20000mAh',
            'description' => 'Power bank de 20000mAh con 3 puertos USB, carga rápida de 18W y diseño compacto.',
            'price' => 29990,
            'weight' => 0.4,
            'image_url' => 'https://example.com/images/power-bank.jpg',
            'category_id' => $categoryMap['Electrónica'],
            'stock_quantity' => 80
        ],
        
        // Moda (8 products)
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
        [
            'name' => 'Vestido de Verano Floral',
            'description' => 'Vestido de verano con estampado floral, tela ligera y transpirable, corte A y mangas cortas.',
            'price' => 29990,
            'weight' => 0.3,
            'image_url' => 'https://example.com/images/summer-dress.jpg',
            'category_id' => $categoryMap['Moda'],
            'stock_quantity' => 70
        ],
        [
            'name' => 'Gorra Deportiva UV',
            'description' => 'Gorra deportiva con protección UV 50+, ajustable, transpirable y diseño moderno.',
            'price' => 9990,
            'weight' => 0.1,
            'image_url' => 'https://example.com/images/sports-cap.jpg',
            'category_id' => $categoryMap['Moda'],
            'stock_quantity' => 120
        ],
        [
            'name' => 'Bufanda de Lana Premium',
            'description' => 'Bufanda de lana merina de alta calidad, tejido artesanal, suave al tacto y disponible en varios colores.',
            'price' => 24990,
            'weight' => 0.3,
            'image_url' => 'https://example.com/images/wool-scarf.jpg',
            'category_id' => $categoryMap['Moda'],
            'stock_quantity' => 55
        ],
        
        // Hogar (8 products)
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
        [
            'name' => 'Set de Vajilla de Porcelana',
            'description' => 'Set de 12 piezas de vajilla de porcelana, diseño clásico blanco, apto para microondas y lavavajillas.',
            'price' => 49990,
            'weight' => 2.0,
            'image_url' => 'https://example.com/images/dinnerware-set.jpg',
            'category_id' => $categoryMap['Hogar'],
            'stock_quantity' => 30
        ],
        [
            'name' => 'Aromaterapia Difusor de Aceites',
            'description' => 'Difusor de aceites esenciales con luces LED, temporizador, apagado automático y capacidad de 300ml.',
            'price' => 29990,
            'weight' => 0.5,
            'image_url' => 'https://example.com/images/oil-diffuser.jpg',
            'category_id' => $categoryMap['Hogar'],
            'stock_quantity' => 65
        ],
        [
            'name' => 'Manta de Pelo Suave',
            'description' => 'Manta de pelo suave con borde de microfibra, lavable a máquina, ideal para sofá o cama.',
            'price' => 19990,
            'weight' => 1.2,
            'image_url' => 'https://example.com/images/soft-blanket.jpg',
            'category_id' => $categoryMap['Hogar'],
            'stock_quantity' => 90
        ],
        
        // Deportes (8 products)
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
        [
            'name' => 'Mochila Deportiva Impermeable',
            'description' => 'Mochila deportiva con compartimento para laptop, bolsillos múltiples, material impermeable y correas acolchadas.',
            'price' => 24990,
            'weight' => 0.7,
            'image_url' => 'https://example.com/images/sports-backpack.jpg',
            'category_id' => $categoryMap['Deportes'],
            'stock_quantity' => 75
        ],
        [
            'name' => 'Set de Yoga Blocks y Strap',
            'description' => 'Set de 2 bloques de yoga de corcho y cinta elástica de 1.8m, materiales ecológicos y antideslizantes.',
            'price' => 19990,
            'weight' => 0.6,
            'image_url' => 'https://example.com/images/yoga-accessories.jpg',
            'category_id' => $categoryMap['Deportes'],
            'stock_quantity' => 95
        ],
        [
            'name' => 'Guantes de Entrenamiento',
            'description' => 'Guantes de entrenamiento con protección palmar, ajuste regulable, transpirables y diseño anatómico.',
            'price' => 14990,
            'weight' => 0.3,
            'image_url' => 'https://example.com/images/training-gloves.jpg',
            'category_id' => $categoryMap['Deportes'],
            'stock_quantity' => 110
        ],
        
        // Belleza (8 products)
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
        ],
        [
            'name' => 'Kit de Brochas de Maquillaje',
            'description' => 'Kit de 12 brochas de maquillaje con cerdas sintéticas suaves, mango de madera y estuche elegante.',
            'price' => 19990,
            'weight' => 0.3,
            'image_url' => 'https://example.com/images/makeup-brushes.jpg',
            'category_id' => $categoryMap['Belleza'],
            'stock_quantity' => 90
        ],
        [
            'name' => 'Máquina de Afeitar Eléctrica',
            'description' => 'Máquina de afeitar eléctrica con 3 cabezales, sistema de flotación flexible, lavable bajo agua y 60 minutos de autonomía.',
            'price' => 49990,
            'weight' => 0.4,
            'image_url' => 'https://example.com/images/electric-shaver.jpg',
            'category_id' => $categoryMap['Belleza'],
            'stock_quantity' => 40
        ],
        [
            'name' => 'Set de Cepillos de Dientes Eléctricos',
            'description' => 'Set de 2 cepillos de dientes eléctricos con 3 modos de limpieza, temporizador y cabezales de repuesto.',
            'price' => 39990,
            'weight' => 0.3,
            'image_url' => 'https://example.com/images/electric-toothbrush.jpg',
            'category_id' => $categoryMap['Belleza'],
            'stock_quantity' => 60
        ]
    ];
    
    // Add products to database
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
            echo "Added product: " . $product['name'] . "\n";
        } catch (PDOException $e) {
            echo "Error adding product " . $product['name'] . ": " . $e->getMessage() . "\n";
        }
    }
    
    echo "Total products added: " . count($products) . "\n";
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

try {
    echo "Starting to add categories and products...\n";
    
    // Add categories
    addCategories($pdo);
    
    // Get admin ID
    $admin_id = getAdminId($pdo);
    echo "Using admin ID: " . $admin_id . "\n";
    
    // Add products
    addProducts($pdo, $admin_id);
    
    echo "Finished adding categories and products.\n";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>