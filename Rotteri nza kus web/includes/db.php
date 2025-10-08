<?php
/**
 * Base de conexión PDO unificada para la app.
 * Sustituye el wrapper previo que dependía de ../../includes/db.php (inexistente en este entorno)
 * y asegura la creación mínima de tablas críticas (users, admins, cart, notifications) si faltan.
 */

if (!isset($pdo) || !$pdo instanceof PDO) {
    $DB_HOST = 'localhost';
    $DB_NAME = 'rotteri_nza_kus';
    $DB_USER = 'root';
    $DB_PASS = '';

    try {
        $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        // Falla dura: sin conexión no podemos continuar.
        http_response_code(500);
        echo 'Error de conexión a la base de datos: ' . htmlspecialchars($e->getMessage());
        exit;
    }
}

// --- Creación / reparación mínima de tablas ---
try {
    // Tabla users (si desapareció / corrupta)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        username VARCHAR(50) UNIQUE NOT NULL,\n        email VARCHAR(100) UNIQUE NOT NULL,\n        password VARCHAR(255) NOT NULL,\n        first_name VARCHAR(50) NOT NULL,\n        last_name VARCHAR(50) NOT NULL,\n        role ENUM('customer','admin') DEFAULT 'customer',\n        phone VARCHAR(20),\n        address TEXT,\n        city VARCHAR(50),\n        country VARCHAR(50),\n        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Tabla admins base (no crea filas)
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        user_id INT NOT NULL,\n        company_name VARCHAR(255) NULL,\n        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n        UNIQUE KEY uniq_user (user_id)\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Cart mínima
    $pdo->exec("CREATE TABLE IF NOT EXISTS cart (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        user_id INT NOT NULL,\n        product_id INT NOT NULL,\n        quantity INT NOT NULL DEFAULT 1,\n        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n        INDEX(user_id),\n        INDEX(product_id)\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Notifications mínima (usuarios)
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        user_id INT NOT NULL,\n        title VARCHAR(150) NOT NULL,\n        message TEXT NULL,\n        link VARCHAR(255) NULL,\n        is_read TINYINT(1) NOT NULL DEFAULT 0,\n        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n        INDEX(user_id),\n        INDEX(is_read)\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    // Registrar error en var/ por diagnóstico si existe permisos
    $logDir = __DIR__ . '/../var';
    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
    @file_put_contents($logDir . '/db_bootstrap_errors.log', date('c') . ' :: ' . $e->getMessage() . "\n", FILE_APPEND);
}

// Ajustes evolutivos (columnas nuevas si faltan) - se silencian errores
try { $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20)"); } catch (Throwable $ignore) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address TEXT"); } catch (Throwable $ignore) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN city VARCHAR(50)"); } catch (Throwable $ignore) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN country VARCHAR(50)"); } catch (Throwable $ignore) {}

// --- Verificación de integridad de tabla users; si sigue inaccesible intentar reconstrucción ---
try {
    $pdo->query('SELECT 1 FROM users LIMIT 0');
} catch (Throwable $eCheck) {
    // Fallback: crear una nueva base limpia y cargar schema database.sql
    $fallbackDb = 'rotteri_nza_kus_rebuild';
    try {
        $rootPdo = new PDO("mysql:host={$DB_HOST};charset=utf8mb4", $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `{$fallbackDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $pdo = new PDO("mysql:host={$DB_HOST};dbname={$fallbackDb};charset=utf8mb4", $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Cargar schema desde database.sql si existe
        $schemaFile = realpath(__DIR__ . '/../database.sql');
        if ($schemaFile && is_file($schemaFile)) {
            $sqlRaw = file_get_contents($schemaFile);
            // Eliminar BOM / normalizar saltos
            $sqlRaw = preg_replace('/\xEF\xBB\xBF/', '', $sqlRaw);
            // Remover líneas de CREATE DATABASE / USE
            $lines = preg_split('/\r?\n/', $sqlRaw);
            $filtered = [];
            foreach ($lines as $ln) {
                $trim = trim($ln);
                if ($trim === '' || str_starts_with($trim, '--')) continue;
                if (preg_match('/^CREATE\s+DATABASE/i', $trim)) continue;
                if (preg_match('/^USE\s+/i', $trim)) continue;
                $filtered[] = $ln;
            }
            $sqlFiltered = implode("\n", $filtered);
            // Partir por ; cuidando no romper dentro de posibles definiciones (simple split)
            $stmts = array_filter(array_map('trim', preg_split('/;\s*\n/', $sqlFiltered)));
            foreach ($stmts as $stmt) {
                if ($stmt === '') continue;
                try { $pdo->exec($stmt); } catch (Throwable $ie) {
                    // Registrar pero continuar
                    $logDir = __DIR__ . '/../var';
                    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
                    @file_put_contents($logDir . '/db_rebuild_errors.log', date('c') . ' :: ' . $ie->getMessage() . "\nSQL: " . $stmt . "\n", FILE_APPEND);
                }
            }
        }
        // Repetir bootstrap mínimo sobre nueva DB
        try { $pdo->query('SELECT 1 FROM users LIMIT 0'); } catch (Throwable $still) {
            throw $still; // no pudo reconstruir
        }
    } catch (Throwable $rebuildFail) {
        $logDir = __DIR__ . '/../var';
        if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
        @file_put_contents($logDir . '/db_bootstrap_errors.log', date('c') . ' :: FALLBACK FAIL :: ' . $rebuildFail->getMessage() . "\n", FILE_APPEND);
        // Como último recurso devolvemos error claro
        http_response_code(500);
        echo 'Fallo crítico: no se pudo acceder ni reconstruir la tabla users. ' . htmlspecialchars($rebuildFail->getMessage());
        exit;
    }
}

// --- Auto seed de un admin si no existe ninguno ---
try {
    $st = $pdo->query("SELECT id FROM users WHERE role='admin' LIMIT 1");
    $hasAdmin = (bool)$st->fetchColumn();
    if (!$hasAdmin) {
        $seedEmail = 'admin_auto@localhost';
        $seedPass  = 'AdminAuto123!'; // Cambiar después del primer login
        $username  = 'adminauto';
        $hash = password_hash($seedPass, PASSWORD_BCRYPT, ['cost'=>10]);
        $ins = $pdo->prepare('INSERT INTO users (username,email,password,first_name,last_name,role) VALUES (?,?,?,?,?,"admin")');
        $ins->execute([$username,$seedEmail,$hash,'Admin','Auto']);
        $uid = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO admins (user_id, company_name) VALUES (?,?)')->execute([$uid,'AutoSeed']);
        $credFileDir = __DIR__ . '/../var';
        if (!is_dir($credFileDir)) { @mkdir($credFileDir, 0775, true); }
        @file_put_contents($credFileDir . '/initial_admin_credentials.txt', "email={$seedEmail}\npassword={$seedPass}\nuser_id={$uid}\n" . date('c'));
    }
} catch (Throwable $e) {
    // silencioso; no impedir ejecución del resto
}

?>
