<?php
// Seed ~56 products for a specific admin by email, using image links
// Usage (CLI): php seed_admin_products.php --yes [--count=56]
// Usage (Web - admin only): seed_admin_products.php?confirm=1

require_once '../includes/db.php';
require_once '../includes/auth.php';

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    // Only admins via web can trigger
    requireAdmin();
    if (empty($_GET['confirm'])) {
        http_response_code(400);
        echo 'Falta confirm=1';
        exit;
    }
}

// Parse CLI args
$args = [];
if ($isCli && isset($argv)) {
    foreach ($argv as $a) {
        if (strpos($a, '--') === 0) {
            $parts = explode('=', substr($a, 2), 2);
            $args[$parts[0]] = $parts[1] ?? true;
        }
    }
    if (empty($args['yes']) && empty($args['y'])) {
        fwrite(STDERR, "Añade --yes para confirmar. Opcional: --count=56\n");
        exit(1);
    }
}

$targetEmail = 'etebachalegroup@gmail.com';
$count = 56;
if ($isCli && isset($args['count'])) {
    $n = (int)$args['count'];
    if ($n > 0 && $n <= 200) $count = $n;
}

// Ensure required schema
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        weight DECIMAL(10,2) NULL,
        image_url VARCHAR(500) NULL,
        tags VARCHAR(500) NULL,
        category_id INT NULL,
        admin_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(category_id), INDEX(admin_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) { /* ignore */ }
try { $pdo->exec("ALTER TABLE products ADD COLUMN admin_id INT NULL"); } catch (Exception $ignore) {}
try { $pdo->exec("ALTER TABLE products ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $ignore) {}
try { $pdo->exec("ALTER TABLE products ADD COLUMN tags VARCHAR(500) NULL"); } catch (Exception $ignore) {}

// Find or create admin user by email
$stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ?');
$stmt->execute([$targetEmail]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    $pwd = password_hash('changeme123', PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO users (email, password, role, first_name, last_name) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([$targetEmail, $pwd, 'admin', 'Etebachale', 'Group']);
    $userId = (int)$pdo->lastInsertId();
} else {
    $userId = (int)$user['id'];
    if (($user['role'] ?? 'user') !== 'admin' && ($user['role'] ?? '') !== 'super_admin') {
        try { $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute(['admin', $userId]); } catch (Exception $ignore) {}
    }
}

// Ensure admins profile
$stmt = $pdo->prepare('SELECT id FROM admins WHERE user_id = ?');
$stmt->execute([$userId]);
$adminRow = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$adminRow) {
    $ins = $pdo->prepare('INSERT INTO admins (user_id, company_name) VALUES (?, ?)');
    try { $pdo->exec("ALTER TABLE admins ADD COLUMN company_name VARCHAR(255) NULL"); } catch (Exception $ignore) {}
    $ins->execute([$userId, 'Etebachale Group']);
    $adminId = (int)$pdo->lastInsertId();
} else {
    $adminId = (int)$adminRow['id'];
}

// Create categories if missing
$categoryNames = [
    'Bebidas','Snacks','Frutas','Verduras','Lácteos','Carnes','Panadería','Higiene','Limpieza','Electrónica'
];
$catIdByName = [];
foreach ($categoryNames as $cn) {
    try {
        $pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$cn]);
    } catch (Exception $ignore) {}
    $row = $pdo->prepare('SELECT id FROM categories WHERE name = ?');
    $row->execute([$cn]);
    $cid = $row->fetchColumn();
    if ($cid) $catIdByName[$cn] = (int)$cid;
}

// Helper to generate a placeholder image URL
$imgFor = function($seed) {
    $seed = preg_replace('/[^a-z0-9]+/i','-', strtolower($seed));
    return 'https://picsum.photos/seed/' . $seed . '/600/400';
};

// Build product templates
$templates = [
    ['Bebida Refrescante', 'Bebidas', 'bebida, refresco, frío'],
    ['Snack Crujiente', 'Snacks', 'snack, crujiente, salado'],
    ['Manzana Roja', 'Frutas', 'fruta, manzana, fresca'],
    ['Banana Dulce', 'Frutas', 'fruta, banana, energía'],
    ['Leche Entera', 'Lácteos', 'lácteo, leche, calcio'],
    ['Yogur Natural', 'Lácteos', 'yogur, probiótico, saludable'],
    ['Pechuga de Pollo', 'Carnes', 'carne, pollo, proteína'],
    ['Pan Integral', 'Panadería', 'pan, integral, fibra'],
    ['Jabón Líquido', 'Higiene', 'jabón, higiene, manos'],
    ['Detergente Multiusos', 'Limpieza', 'limpieza, hogar, multiusos'],
    ['Auriculares Inalámbricos', 'Electrónica', 'audio, bluetooth, música'],
    ['Zanahoria', 'Verduras', 'verdura, zanahoria, vitamina a'],
];

// Generate N products by cycling templates with variants
$ins = $pdo->prepare('INSERT INTO products (name, description, price, weight, image_url, category_id, admin_id, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$sel = $pdo->prepare('SELECT id FROM products WHERE admin_id = ? AND name = ?');

$created = 0; $skipped = 0;
for ($i = 1; $i <= $count; $i++) {
    $t = $templates[($i - 1) % count($templates)];
    [$baseName, $catName, $tags] = $t;
    $variant = ceil($i / count($templates));
    $name = $baseName . ' ' . $variant;
    $desc = 'Producto de ejemplo ' . $baseName . ' variante ' . $variant . ' con excelente calidad y precio competitivo.';
    $price = number_format(mt_rand(100, 5000) / 100, 2, '.', '');
    $weight = number_format(mt_rand(50, 5000) / 1000, 2, '.', ''); // kg
    $image = $imgFor($baseName . '-' . $variant);
    $cid = $catIdByName[$catName] ?? null;

    // Skip if same name already exists for this admin
    $sel->execute([$adminId, $name]);
    if ($sel->fetch(PDO::FETCH_ASSOC)) { $skipped++; continue; }

    try {
        $ins->execute([$name, $desc, $price, $weight, $image, $cid, $adminId, $tags]);
        $created++;
    } catch (Exception $e) {
        $skipped++;
    }
}

$msg = "Seed finalizado. Creados: $created, Omitidos: $skipped, Total deseado: $count";
if ($isCli) {
    fwrite(STDOUT, $msg . "\n");
} else {
    echo htmlspecialchars($msg);
}

?>
