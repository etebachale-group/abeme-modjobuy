<?php
/**
 * reset_admin_account.php
 * -----------------------------------------------------------------------------
 * Script de un solo uso para:
 *  1. Validar un token secreto (para impedir uso accidental o ajeno)
 *  2. Eliminar cualquier rastro de administradores previos:
 *     - Fila(s) en tabla principal `admins` (si existe)
 *     - Fila(s) en tabla fallback `administrador` (si existe)
 *     - Usuarios con role='admin' excepto el que vamos a recrear
 *  3. Recrear un usuario administrador limpio con los datos enviados (POST)
 *  4. Crear su fila en la tabla admin definitiva (`admins`) (creándola si falta)
 *  5. Opcionalmente eliminar la tabla fallback para evitar confusión futura
 *
 *  IMPORTANTE: Borrar este archivo inmediatamente después de usarlo.
 * -----------------------------------------------------------------------------
 * Parámetros esperados (POST):
 *   token          => Debe coincidir con TOKEN_RESET_ADMIN definido abajo
 *   email          => Nuevo email admin
 *   password       => Nueva contraseña (mín 8 chars)
 *   first_name     => Nombre
 *   last_name      => Apellido
 *   company_name   => (opcional) Nombre empresa
 *   drop_fallback  => '1' para eliminar tabla fallback `administrador` si existe
 *
 * Respuesta: JSON
 */

header('Content-Type: application/json; charset=utf-8');

// ----------- CONFIGURACIÓN DEL TOKEN DE SEGURIDAD -----------
// Genera un token aleatorio y reemplázalo aquí SOLO mientras usas este script.
// Ejemplo rápido en PHP CLI: php -r "echo bin2hex(random_bytes(16));"
const TOKEN_RESET_ADMIN = 'CAMBIA_ESTE_TOKEN';

// Bloquear métodos no POST para no facilitar enumeración casual
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido (usa POST).']);
    exit;
}

// Simple rate limit básico por IP (en memoria de sesión)
session_start();
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!isset($_SESSION['reset_attempts'])) { $_SESSION['reset_attempts'] = []; }
$_SESSION['reset_attempts'] = array_filter($_SESSION['reset_attempts'], fn($ts) => $ts > time() - 300);
$_SESSION['reset_attempts'][] = time();
if (count($_SESSION['reset_attempts']) > 10) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Demasiados intentos.']);
    exit;
}

// Validar token
$token = $_POST['token'] ?? '';
if (!hash_equals(TOKEN_RESET_ADMIN, $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token inválido.']);
    exit;
}

// Recoger parámetros
$email        = trim($_POST['email'] ?? '');
$password     = (string)($_POST['password'] ?? '');
$first_name   = trim($_POST['first_name'] ?? '');
$last_name    = trim($_POST['last_name'] ?? '');
$company_name = trim($_POST['company_name'] ?? '');
$dropFallback = isset($_POST['drop_fallback']) && $_POST['drop_fallback'] === '1';

$errors = [];
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Email inválido.'; }
if (strlen($password) < 8) { $errors[] = 'Password debe tener al menos 8 caracteres.'; }
if ($first_name === '') { $errors[] = 'first_name requerido.'; }
if ($last_name === '') { $errors[] = 'last_name requerido.'; }
if ($errors) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Validación fallida', 'errors' => $errors]);
    exit;
}

// Cargar conexión reutilizando include primario
try {
    require_once __DIR__ . '/../includes/db.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo cargar conexión DB', 'error' => $e->getMessage()]);
    exit;
}

if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Conexión PDO no disponible.']);
    exit;
}

// Detectar nombre real de base de datos
try {
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
} catch (Exception $e) { $dbName = null; }

// Funciones auxiliares
function tableExists(PDO $pdo, string $name): bool {
    try {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$name]);
        return (bool)$stmt->fetchColumn();
    } catch (Exception $e) { return false; }
}

function dropTableIfExists(PDO $pdo, string $name): bool {
    if (!tableExists($pdo, $name)) return true;
    try { $pdo->exec("DROP TABLE `{$name}`"); return true; } catch (Exception $e) { return false; }
}

function ensureAdminsTable(PDO $pdo): bool {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `admins` (\n            id INT AUTO_INCREMENT PRIMARY KEY,\n            user_id INT NOT NULL,\n            company_name VARCHAR(255) NULL,\n            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n            UNIQUE KEY uniq_user (user_id)\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return true;
    } catch (Exception $e) { return false; }
}

$report = [
    'dropped' => [ 'admins' => false, 'administrador' => false, 'other_admin_users' => 0 ],
    'created_user_id' => null,
    'created_admin_id' => null,
    'db' => $dbName,
];

try {
    $pdo->beginTransaction();

    // 1. Eliminar filas previa(s) en tablas de administradores
    if (tableExists($pdo, 'admins')) {
        $pdo->exec('DELETE FROM `admins`');
        $report['dropped']['admins'] = true; // (realmente vaciado, no drop)
    }
    if (tableExists($pdo, 'administrador')) {
        if ($dropFallback) {
            dropTableIfExists($pdo, 'administrador');
            $report['dropped']['administrador'] = true; // drop real
        } else {
            $pdo->exec('DELETE FROM `administrador`');
            $report['dropped']['administrador'] = true; // vaciado
        }
    }

    // 2. Eliminar otros usuarios con rol admin
    $stmtAdmins = $pdo->query("SELECT id,email FROM users WHERE role='admin'");
    $adminsBefore = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);
    if ($adminsBefore) {
        // Borramos todos para limpiar (cascade eliminará filas orphan)
        $ids = array_column($adminsBefore, 'id');
        if ($ids) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $del = $pdo->prepare("DELETE FROM users WHERE id IN ($in)");
            $del->execute($ids);
            $report['dropped']['other_admin_users'] = count($ids);
        }
    }

    // 3. Crear usuario admin nuevo
    $username = preg_replace('/[^a-z0-9_]+/i','', substr(strtolower(explode('@',$email)[0]),0,30));
    if ($username === '') { $username = 'admin'.time(); }
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost'=>10]);

    $insUser = $pdo->prepare('INSERT INTO users (username,email,password,first_name,last_name,role) VALUES (?,?,?,?,?,"admin")');
    $insUser->execute([$username,$email,$hash,$first_name,$last_name]);
    $userId = (int)$pdo->lastInsertId();
    $report['created_user_id'] = $userId;

    // 4. Asegurar tabla admins y crear fila
    ensureAdminsTable($pdo);
    $insAdmin = $pdo->prepare('INSERT INTO admins (user_id, company_name) VALUES (?,?)');
    $insAdmin->execute([$userId,$company_name]);
    $adminId = (int)$pdo->lastInsertId();
    $report['created_admin_id'] = $adminId;

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Administrador reseteado y recreado correctamente',
        'report' => $report
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Fallo durante el reseteo',
        'error' => $e->getMessage(),
        'report' => $report
    ]);
}

// FIN - BORRAR ESTE ARCHIVO TRAS SU USO
