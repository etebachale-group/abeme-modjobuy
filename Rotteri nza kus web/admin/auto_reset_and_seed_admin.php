<?php
/**
 * auto_reset_and_seed_admin.php
 * ------------------------------------------------------------------
 * Uso: Accede UNA sola vez vía navegador o curl.
 *  - Elimina todos los usuarios con role='admin'
 *  - Borra / recrea tabla `admins`
 *  - Elimina tabla fallback `administrador` si existe
 *  - Crea un nuevo super admin limpio con credenciales provisionales
 *  - Inserta su fila en `admins`
 *  - Crea marcador en var/admin_reset_done.txt con detalles
 *  - Se AUTO‑ELIMINA (unlink) tras ejecutarse con éxito
 *
 * Seguridad: No requiere token (pedido: "haz todo automaticamente").
 *            NO dejar este archivo subido tras completarse; se borra solo.
 *            Si falla antes de borrarse, bórralo manualmente cuando termines.
 * ------------------------------------------------------------------
 * Cambia las credenciales abajo ANTES de la primera ejecución si deseas otras.
 */

// ========= CONFIGURACIÓN =========
$NEW_ADMIN_EMAIL  = 'admin_recreado@localhost';
$NEW_ADMIN_PASS   = 'CambiarAhora123!';       // Cambiar inmediatamente tras login
$NEW_ADMIN_FIRST  = 'Admin';
$NEW_ADMIN_LAST   = 'Principal';
$NEW_COMPANY_NAME = 'Organizacion';

// ========= INICIO SCRIPT =========
header('Content-Type: application/json; charset=utf-8');

$root = realpath(__DIR__ . '/..');
$varDir = $root . DIRECTORY_SEPARATOR . 'var';
if (!is_dir($varDir)) { @mkdir($varDir, 0775, true); }
$markerFile = $varDir . DIRECTORY_SEPARATOR . 'admin_reset_done.txt';

if (is_file($markerFile)) {
    http_response_code(410);
    echo json_encode([
        'success' => false,
        'message' => 'Reset ya fue ejecutado anteriormente. Borrar este archivo.',
        'marker'  => basename($markerFile)
    ]);
    exit;
}

// Intentar cargar conexión existente
$pdo = null;
try {
    @require_once __DIR__ . '/../includes/db.php'; // puede o no definir $pdo
} catch (Throwable $ignore) {}

// Fallback si $pdo no está disponible
if (!isset($pdo) || !$pdo) {
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=rotteri_nza_kus;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'No se pudo establecer conexión PDO', 'error' => $e->getMessage()]);
        exit;
    }
}

// Helper
$exec = function(string $sql) use ($pdo) {
    try { $pdo->exec($sql); } catch (Throwable $e) { /* ignorar */ }
};

// Recolectar reporte
$report = [
    'db' => null,
    'dropped_tables' => [],
    'deleted_admin_users' => 0,
    'new_user_id' => null,
    'new_admin_id' => null,
    'email' => $NEW_ADMIN_EMAIL,
];

try { $report['db'] = $pdo->query('SELECT DATABASE()')->fetchColumn(); } catch (Throwable $e) {}

try {
    $pdo->beginTransaction();

    // Asegurar tabla users mínima (por si falta role)
    $exec("ALTER TABLE users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'customer'");
    $exec("ALTER TABLE users ADD COLUMN first_name VARCHAR(100) NULL");
    $exec("ALTER TABLE users ADD COLUMN last_name VARCHAR(100) NULL");

    // Eliminar tabla fallback 'administrador' si existe
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name='administrador'");
        $stmt->execute();
        if ($stmt->fetchColumn()) {
            $exec('DROP TABLE IF EXISTS `administrador`');
            $report['dropped_tables'][] = 'administrador';
        }
    } catch (Throwable $e) {}

    // Re-crear tabla admins limpia
    $exec('DROP TABLE IF EXISTS `admins`');
    $exec("CREATE TABLE `admins` (\n        id INT AUTO_INCREMENT PRIMARY KEY,\n        user_id INT NOT NULL,\n        company_name VARCHAR(255) NULL,\n        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n        UNIQUE KEY uniq_user (user_id)\n    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $report['dropped_tables'][] = 'admins(recreated)';

    // Borrar usuarios admin previos
    $prev = $pdo->query("SELECT id FROM users WHERE role='admin'")->fetchAll(PDO::FETCH_COLUMN);
    if ($prev) {
        $in = implode(',', array_fill(0, count($prev), '?'));
        $del = $pdo->prepare("DELETE FROM users WHERE id IN ($in)");
        $del->execute($prev);
        $report['deleted_admin_users'] = count($prev);
    }

    // Crear nuevo usuario admin
    $username = preg_replace('/[^a-z0-9_]+/i','', substr(strtolower(explode('@',$NEW_ADMIN_EMAIL)[0]),0,30));
    if ($username === '') { $username = 'admin'.time(); }
    $hash = password_hash($NEW_ADMIN_PASS, PASSWORD_BCRYPT, ['cost' => 10]);
    $insU = $pdo->prepare('INSERT INTO users (username,email,password,first_name,last_name,role) VALUES (?,?,?,?,?,"admin")');
    $insU->execute([$username,$NEW_ADMIN_EMAIL,$hash,$NEW_ADMIN_FIRST,$NEW_ADMIN_LAST]);
    $uid = (int)$pdo->lastInsertId();
    $report['new_user_id'] = $uid;

    // Insertar fila admin
    $insA = $pdo->prepare('INSERT INTO admins (user_id, company_name) VALUES (?,?)');
    $insA->execute([$uid,$NEW_COMPANY_NAME]);
    $report['new_admin_id'] = (int)$pdo->lastInsertId();

    $pdo->commit();

    // Escribir marcador
    @file_put_contents($markerFile, json_encode(['ts'=>time(),'email'=>$NEW_ADMIN_EMAIL,'user_id'=>$uid,'admin_id'=>$report['new_admin_id']], JSON_PRETTY_PRINT));

    $output = [
        'success' => true,
        'message' => 'Admin reseteado y recreado exitosamente. ESTE SCRIPT SE AUTODESTRUIRÁ.',
        'credentials' => [
            'email' => $NEW_ADMIN_EMAIL,
            'password' => $NEW_ADMIN_PASS,
        ],
        'report' => $report,
        'next_steps' => 'Inicia sesión y cambia la contraseña de inmediato. Borra cualquier caché y verifica acceso.'
    ];

    // Autodestruir script
    $self = __FILE__;
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    // Intentar borrar al final (no detener salida si falla)
    @unlink($self);
    exit;
} catch (Throwable $e) {
    @file_put_contents($markerFile . '.error.log', date('c') . " :: " . $e->getMessage() . "\n", FILE_APPEND);
    if ($pdo && $pdo->inTransaction()) { try { $pdo->rollBack(); } catch (Throwable $ignore) {} }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error durante el reseteo automático',
        'error' => $e->getMessage(),
        'report' => $report
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>
