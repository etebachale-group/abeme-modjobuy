<?php
/**
 * force_repair_and_seed_admin.php
 * ----------------------------------------------------------------------------
 * Uso: Ejecutar UNA sola vez (navegador o curl) cuando la tabla `users` está
 *       corrupta / desaparecida (error InnoDB 1932) y necesitas reconstruir
 *       todo y crear un admin limpio automáticamente.
 *
 * Qué hace (riesgo: DESTRUYE datos relacionados a usuarios previos):
 *   1. Desactiva FOREIGN_KEY_CHECKS
 *   2. Elimina (DROP) y recrea tabla `users` si está ausente o corrupta
 *   3. Limpia tablas dependientes: admins, cart, orders, order_items,
 *      notifications, product_history (TRUNCATE) y limpia referencias en products
 *   4. Re-crea tabla admins (DROP + CREATE)
 *   5. Crea un nuevo usuario admin con credenciales provisionales
 *   6. Inserta fila en admins
 *   7. Reactiva FOREIGN_KEY_CHECKS
 *   8. Escribe marcador en var/admin_force_repair_done.txt
 *   9. Intenta autodestruirse (unlink)
 *
 * ADVERTENCIA: Esto ELIMINA todos los usuarios, pedidos, carrito, historial
 *              y notificaciones. Los productos se mantienen pero pierden
 *              su administrador (admin_id se pone NULL).
 *              Haz un backup si necesitas conservar algo.
 *
 * Ajusta las credenciales del nuevo admin abajo antes de ejecutar.
 * ----------------------------------------------------------------------------
 */
header('Content-Type: application/json; charset=utf-8');

// ================== CONFIGURACIÓN NUEVO ADMIN ==================
$NEW_ADMIN_EMAIL  = 'admin_reparado@localhost';
$NEW_ADMIN_PASS   = 'CambiarYa123!'; // Cambiar tras primer login
$NEW_ADMIN_FIRST  = 'Admin';
$NEW_ADMIN_LAST   = 'Reparado';
$NEW_COMPANY_NAME = 'Organizacion';

// ===============================================================
$root = realpath(__DIR__ . '/..');
$varDir = $root . DIRECTORY_SEPARATOR . 'var';
if (!is_dir($varDir)) { @mkdir($varDir, 0775, true); }
$markerFile = $varDir . DIRECTORY_SEPARATOR . 'admin_force_repair_done.txt';

if (is_file($markerFile)) {
    http_response_code(410);
    echo json_encode([
        'success' => false,
        'message' => 'El script ya fue ejecutado. Bórralo si no lo necesitas.'
    ]);
    exit;
}

// Intentar conexión estándar
$pdo = null;
try { @require_once __DIR__ . '/../includes/db.php'; } catch (Throwable $e) {}
if (!isset($pdo) || !$pdo) {
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=rotteri_nza_kus;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No se pudo conectar a la base de datos', 'error' => $e->getMessage()]);
        exit;
    }
}

$report = [
    'db' => null,
    'recreated_users' => false,
    'dropped_tables' => [],
    'truncated_tables' => [],
    'products_admin_nullified' => 0,
    'new_user_id' => null,
    'new_admin_id' => null,
    'email' => $NEW_ADMIN_EMAIL,
];
try { $report['db'] = $pdo->query('SELECT DATABASE()')->fetchColumn(); } catch (Throwable $e) {}

// Helpers
$tableExists = function(string $name) use ($pdo) : bool {
    try {
        $st = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name=?');
        $st->execute([$name]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) { return false; }
};

$safeExec = function(string $sql) use ($pdo) { try { $pdo->exec($sql); } catch (Throwable $e) { /* ignore */ } };

// Detectar corrupción de users (SELECT simple)
$needsUsersRecreate = false;
try {
    if (!$tableExists('users')) {
        $needsUsersRecreate = true;
    } else {
        $pdo->query('SELECT 1 FROM users LIMIT 0');
    }
} catch (Throwable $e) {
    // InnoDB 1932 u otros errores -> forzar recreate
    $needsUsersRecreate = true;
}

try {
    // No usamos transacción porque habrá DDL que hace commits implícitos
    $safeExec('SET FOREIGN_KEY_CHECKS=0');

    if ($needsUsersRecreate) {
        $safeExec('DROP TABLE IF EXISTS `users`');
        // Crear según el esquema original (ajustado a ENUM customer/admin)
        $safeExec("CREATE TABLE `users` (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            username VARCHAR(50) UNIQUE NOT NULL,\n            email VARCHAR(100) UNIQUE NOT NULL,\n            password VARCHAR(255) NOT NULL,\n            first_name VARCHAR(50) NOT NULL,\n            last_name VARCHAR(50) NOT NULL,\n            role ENUM('customer','admin') DEFAULT 'customer',\n            phone VARCHAR(20),\n            address TEXT,\n            city VARCHAR(50),\n            country VARCHAR(50),\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $report['recreated_users'] = true;
    }

    // Limpiar o recrear tablas dependientes
    $dependentTruncate = ['admins','cart','orders','order_items','notifications','product_history'];
    foreach ($dependentTruncate as $t) {
        if ($tableExists($t)) {
            // admins se recrea de cero luego
            if ($t === 'admins') continue;
            $safeExec('TRUNCATE TABLE `'.$t.'`');
            $report['truncated_tables'][] = $t;
        }
    }

    // Eliminar fallback si existe
    if ($tableExists('administrador')) {
        $safeExec('DROP TABLE IF EXISTS `administrador`');
        $report['dropped_tables'][] = 'administrador';
    }

    // Re-crear admins limpio
    $safeExec('DROP TABLE IF EXISTS `admins`');
    $safeExec("CREATE TABLE `admins` (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        user_id INT NOT NULL,\n        company_name VARCHAR(255) NULL,\n        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n        UNIQUE KEY uniq_user (user_id)\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $report['dropped_tables'][] = 'admins';

    // Limpiar referencias admin en products (si existe)
    if ($tableExists('products')) {
        try {
            $affected = $pdo->exec('UPDATE products SET admin_id = NULL');
            if ($affected === false) { $affected = 0; }
            $report['products_admin_nullified'] = $affected;
        } catch (Throwable $e) {}
    }

    // Crear nuevo admin
    $username = preg_replace('/[^a-z0-9_]+/i','', substr(strtolower(explode('@',$NEW_ADMIN_EMAIL)[0]),0,30));
    if ($username === '') { $username = 'admin'.time(); }
    $hash = password_hash($NEW_ADMIN_PASS, PASSWORD_BCRYPT, ['cost'=>10]);
    $insU = $pdo->prepare('INSERT INTO users (username,email,password,first_name,last_name,role) VALUES (?,?,?,?,?,"admin")');
    $insU->execute([$username,$NEW_ADMIN_EMAIL,$hash,$NEW_ADMIN_FIRST,$NEW_ADMIN_LAST]);
    $uid = (int)$pdo->lastInsertId();
    $report['new_user_id'] = $uid;

    $insA = $pdo->prepare('INSERT INTO admins (user_id, company_name) VALUES (?,?)');
    $insA->execute([$uid,$NEW_COMPANY_NAME]);
    $report['new_admin_id'] = (int)$pdo->lastInsertId();

    $safeExec('SET FOREIGN_KEY_CHECKS=1');

    // Escribir marcador
    @file_put_contents($markerFile, json_encode([
        'ts'=>time(),
        'email'=>$NEW_ADMIN_EMAIL,
        'user_id'=>$report['new_user_id'],
        'admin_id'=>$report['new_admin_id']
    ], JSON_PRETTY_PRINT));

    $output = [
        'success' => true,
        'message' => 'Reparación forzada completada. ESTE SCRIPT SE AUTODESTRUIRÁ.',
        'credentials' => [
            'email' => $NEW_ADMIN_EMAIL,
            'password' => $NEW_ADMIN_PASS
        ],
        'report' => $report,
        'next_steps' => 'Inicia sesión, cambia la contraseña y revisa integridad de productos.'
    ];
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    @unlink(__FILE__);
    exit;
} catch (Throwable $e) {
    @file_put_contents($markerFile.'.error.log', date('c').' :: '.$e->getMessage()."\n", FILE_APPEND);
    try { $safeExec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $ign) {}
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fallo en reparación forzada',
        'error' => $e->getMessage(),
        'report' => $report
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>
