<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$isCli = (PHP_SAPI === 'cli');

if ($isCli) {
    // CLI mode: parse args
    $args = [];
    global $argv;
    foreach ($argv as $a) {
        if (strpos($a, '--') === 0) {
            $parts = explode('=', substr($a, 2), 2);
            $args[$parts[0]] = $parts[1] ?? true;
        }
    }
    $email = $args['email'] ?? '';
    $password = $args['password'] ?? '';
    $first = $args['first'] ?? 'Admin';
    $last = $args['last'] ?? 'User';
    $company = $args['company'] ?? 'Company';
    $count = isset($args['count']) ? max(1, min(200, (int)$args['count'])) : 56;
    if ($email === '' || $password === '') {
        fwrite(STDERR, "Uso: php create_admin_and_seed.php --email=user@example.com --password=Secreto123 [--first=Nombre --last=Apellido --company=Empresa --count=56]\n");
        exit(1);
    }
    $result = create_admin_and_seed($pdo, $email, $password, $first, $last, $company, $count);
    fwrite(STDOUT, $result . "\n");
    exit(0);
}

// Web mode: restrict to super admin
requireSuperAdmin();

function create_admin_and_seed($pdo, $email, $password, $first, $last, $company, $count) {
    // Schema guard
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            company_name VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN admin_id INT NULL"); } catch (Exception $ignore) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $ignore) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN tags VARCHAR(500) NULL"); } catch (Exception $ignore) {}

    // Create or update user
    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $userId = (int)$user['id'];
        // Update password and role to admin
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password = ?, first_name = ?, last_name = ?, role = ? WHERE id = ?')
            ->execute([$hash, $first, $last, 'admin', $userId]);
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO users (email, password, first_name, last_name, role) VALUES (?, ?, ?, ?, ?)')
            ->execute([$email, $hash, $first, $last, 'admin']);
        $userId = (int)$pdo->lastInsertId();
    }

    // Ensure admin profile
    try { $pdo->exec("ALTER TABLE admins ADD COLUMN company_name VARCHAR(255) NULL"); } catch (Exception $ignore) {}
    $stmt = $pdo->prepare('SELECT id FROM admins WHERE user_id = ?');
    $stmt->execute([$userId]);
    $adminId = $stmt->fetchColumn();
    if (!$adminId) {
        $pdo->prepare('INSERT INTO admins (user_id, company_name) VALUES (?, ?)')->execute([$userId, $company]);
        $adminId = (int)$pdo->lastInsertId();
    }

    // Ensure categories
    $categoryNames = [
        'Bebidas','Snacks','Frutas','Verduras','Lácteos','Carnes','Panadería','Higiene','Limpieza','Electrónica'
    ];
    $catIdByName = [];
    foreach ($categoryNames as $cn) {
        try { $pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$cn]); } catch (Exception $ignore) {}
        $row = $pdo->prepare('SELECT id FROM categories WHERE name = ?');
        $row->execute([$cn]);
        $cid = $row->fetchColumn();
        if ($cid) $catIdByName[$cn] = (int)$cid;
    }

    // Image helper
    $imgFor = function($seed) { $seed = preg_replace('/[^a-z0-9]+/i','-', strtolower($seed)); return 'https://picsum.photos/seed/' . $seed . '/600/400'; };

    // Templates
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
        $weight = number_format(mt_rand(50, 5000) / 1000, 2, '.', '');
        $image = $imgFor($baseName . '-' . $variant);
        $cid = $catIdByName[$catName] ?? null;

        $sel->execute([$adminId, $name]);
        if ($sel->fetch(PDO::FETCH_ASSOC)) { $skipped++; continue; }

        try { $ins->execute([$name, $desc, $price, $weight, $image, $cid, $adminId, $tags]); $created++; }
        catch (Exception $e) { $skipped++; }
    }

    return "Admin $email creado/actualizado, perfil #$adminId. Productos creados: $created, omitidos: $skipped.";
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF inválido';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $first = trim($_POST['first'] ?? '');
        $last = trim($_POST['last'] ?? '');
        $company = trim($_POST['company'] ?? '');
        $count = (int)($_POST['count'] ?? 56);
        if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Datos inválidos';
        } else {
            $success = create_admin_and_seed($pdo, $email, $password, $first ?: 'Admin', $last ?: 'User', $company ?: 'Company', max(1, min(200, $count ?: 56)));
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Crear Admin y Sembrar Productos</title>
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .card{max-width:640px;margin:40px auto;padding:20px;border:1px solid #ddd;border-radius:8px;background:#fff;box-shadow:0 4px 12px rgba(0,0,0,.06)}
    .form-group{margin-bottom:12px}
    label{display:block;margin-bottom:6px;font-weight:600}
    input{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px}
    .btn{padding:10px 16px;border:none;border-radius:6px;background:#3498db;color:#fff;font-weight:700;cursor:pointer}
    .alert{padding:10px 14px;border-radius:6px;margin-bottom:12px}
    .alert-success{background:#eafaf1;color:#1e824c}
    .alert-danger{background:#fdecea;color:#c0392b}
  </style>
  <link rel="stylesheet" href="../css/toast.css">
  <script src="../js/toast.js"></script>
  </head>
<body>
  <div class="card">
    <h2>Crear administrador y sembrar productos</h2>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
      <div class="form-group">
        <label>Email</label>
        <input name="email" type="email" required placeholder="admin@example.com">
      </div>
      <div class="form-group">
        <label>Contraseña</label>
        <input name="password" type="password" required placeholder="Mínimo 6 caracteres">
      </div>
      <div class="form-group">
        <label>Nombre</label>
        <input name="first" type="text" placeholder="Nombre">
      </div>
      <div class="form-group">
        <label>Apellido</label>
        <input name="last" type="text" placeholder="Apellido">
      </div>
      <div class="form-group">
        <label>Empresa</label>
        <input name="company" type="text" placeholder="Empresa">
      </div>
      <div class="form-group">
        <label>Cantidad de productos</label>
        <input name="count" type="number" min="1" max="200" value="56">
      </div>
      <button class="btn" type="submit">Crear y Sembrar</button>
    </form>
  </div>
</body>
</html>
