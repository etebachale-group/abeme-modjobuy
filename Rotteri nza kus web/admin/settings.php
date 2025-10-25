<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/admin_table.php';

// Check if user is admin
requireAdmin();

// Get admin ID
$admin_id = getCurrentAdminId($pdo);

// Current user id
$current_user_id = currentUserId();

// Resolve admin table name dynamically (handles corrupted primary 'admins')
$ADMIN_TABLE = 'admins';
if (function_exists('admin_table')) {
    try { $ADMIN_TABLE = admin_table($pdo); } catch (Exception $e) { $ADMIN_TABLE = 'admins'; }
}

// --- Helper functions (local scope) ---
/** Ensure the admin table exists (no force repair of corrupted original). */
function ensureAdminTable(PDO $pdo, string $table): bool {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$table}` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            company_name VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return true;
    } catch (Exception $e) { return false; }
}

/** Fetch admin record by internal admin id */
function fetchAdminRecord(PDO $pdo, string $table, $adminId) {
    if (!$adminId) return null;
    try {
        $stmt = $pdo->prepare("SELECT a.*, u.first_name, u.last_name, u.email FROM `{$table}` a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
        $stmt->execute([$adminId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

// Ensure table (best effort) and fetch record
$adminsBroken = !ensureAdminTable($pdo, $ADMIN_TABLE);
$admin = fetchAdminRecord($pdo, $ADMIN_TABLE, $admin_id);
if (!$admin && !$adminsBroken) {
    // Could be table exists but no record – that's fine
}
if ($admin === null && !$adminsBroken) {
    // If fetch returned null but table creation succeeded, treat as no profile yet, not broken.
    $adminsBroken = false;
}

// Silent one-time auto-repair attempt (GET only) if admins table is broken
if ($adminsBroken && $_SERVER['REQUEST_METHOD'] === 'GET' && empty($_SESSION['auto_repair_attempt_admins'])) {
    // Attempt lightweight recreate only for the chosen table name
    $_SESSION['auto_repair_attempt_admins'] = 1;
    if (ensureAdminTable($pdo, $ADMIN_TABLE)) {
        $admin = fetchAdminRecord($pdo, $ADMIN_TABLE, $admin_id);
        if ($admin !== null) { $adminsBroken = false; }
    }
}

if (!$admin) {
    // Graceful fallback placeholder structure
    $admin = [
        'user_id' => null,
        'company_name' => '',
        'first_name' => '',
        'last_name' => '',
        'email' => ''
    ];
}

$error = '';
$success = '';
$hasAdminProfile = !empty($admin_id) && !empty($admin) && !empty($admin['user_id']);

// Auto-clean orphan uploads once per day on GET
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $uploadsDir = __DIR__ . '/../uploads';
        $real = realpath($uploadsDir);
        if ($real !== false && is_dir($real)) {
            $varDir = __DIR__ . '/../var';
            if (!is_dir($varDir)) { @mkdir($varDir, 0775, true); }
            $marker = $varDir . '/last_uploads_cleanup.txt';
            $now = time();
            $last = is_file($marker) ? (int)@file_get_contents($marker) : 0;
            if ($now - $last > 86400) {
                // Build referenced set
                $referenced = [];
                try {
                    $rs = $pdo->query("SELECT image_url FROM products WHERE image_url LIKE 'uploads/%'");
                    while ($row = $rs->fetch(PDO::FETCH_ASSOC)) {
                        if (!empty($row['image_url'])) { $referenced[$row['image_url']] = true; }
                    }
                } catch (Exception $ignore) {}
                // Scan and delete
                foreach (scandir($real) as $f) {
                    if ($f === '.' || $f === '..' || $f === '.htaccess' || $f === 'index.html') continue;
                    $full = $real . DIRECTORY_SEPARATOR . $f;
                    if (!is_file($full)) continue;
                    $rel = 'uploads/' . $f;
                    if (!isset($referenced[$rel])) { @unlink($full); }
                }
                @file_put_contents($marker, (string)$now);
            }
        }
    } catch (Exception $e) {
        // silent
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation for all POST actions
    if (!csrf_validate($_POST['csrf_token'] ?? '')) {
        $error = 'Token CSRF inválido';
    } else {
    if (isset($_POST['create_admin_profile'])) {
        // Create admin profile if missing
        try {
            ensureAdminTable($pdo, $ADMIN_TABLE);
            $check = $pdo->prepare("SELECT id FROM `{$ADMIN_TABLE}` WHERE user_id = ?");
            $check->execute([$current_user_id]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $ins = $pdo->prepare("INSERT INTO `{$ADMIN_TABLE}` (user_id, company_name) VALUES (?, ?)");
                $ins->execute([$current_user_id, '']);
                $admin_id = (int)$pdo->lastInsertId();
                $success = 'Perfil de administrador creado.';
            } else {
                $admin_id = (int)$row['id'];
                $success = 'Perfil de administrador ya existía.';
            }
        } catch (Exception $e) {
            $error = 'No se pudo crear el perfil de administrador.';
        }
    } elseif (isset($_POST['cleanup_orphan_uploads'])) {
        // Cleanup orphan files in uploads directory
        try {
            $uploadsDir = __DIR__ . '/../uploads';
            $real = realpath($uploadsDir);
            if ($real === false || !is_dir($real)) {
                $success = 'No hay directorio de subidas para limpiar.';
            } else {
                $referenced = [];
                try {
                    $rs = $pdo->query("SELECT image_url FROM products WHERE image_url LIKE 'uploads/%'");
                    while ($row = $rs->fetch(PDO::FETCH_ASSOC)) {
                        if (!empty($row['image_url'])) { $referenced[$row['image_url']] = true; }
                    }
                } catch (Exception $ignore) {}

                $all = 0; $deleted = 0; $errors = 0; $referencedCount = count($referenced);
                foreach (scandir($real) as $f) {
                    if ($f === '.' || $f === '..' || $f === '.htaccess' || $f === 'index.html') continue;
                    $full = $real . DIRECTORY_SEPARATOR . $f;
                    if (!is_file($full)) continue;
                    $all++;
                    $rel = 'uploads/' . $f;
                    if (!isset($referenced[$rel])) {
                        if (@unlink($full)) { $deleted++; } else { $errors++; }
                    }
                }
                $success = "Limpieza completada: archivos totales $all, referenciados $referencedCount, eliminados $deleted" . ($errors? ", errores $errors" : '');
            }
        } catch (Exception $e) {
            $error = 'Error durante la limpieza: ' . $e->getMessage();
        }
    } elseif (isset($_POST['create_new_admin_user'])) {
        // Crear o elevar un usuario a administrador
        $new_email = trim($_POST['new_admin_email'] ?? '');
        $new_pass  = (string)($_POST['new_admin_password'] ?? '');
        $new_first = trim($_POST['new_admin_first_name'] ?? '');
        $new_last  = trim($_POST['new_admin_last_name'] ?? '');
        $new_company = trim($_POST['new_admin_company'] ?? '');

        if ($new_email === '' || $new_pass === '' || $new_first === '' || $new_last === '') {
            $error = 'Todos los campos del nuevo administrador son obligatorios.';
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Correo del nuevo administrador inválido.';
        } elseif (strlen($new_pass) < 8) {
            $error = 'La contraseña del nuevo administrador debe tener al menos 8 caracteres.';
        } else {
            try {
                // Primero asegurar la tabla fuera de la transacción (DDL puede provocar commit implícito)
                ensureAdminTable($pdo, $ADMIN_TABLE);

                $pdo->beginTransaction();
                $st = $pdo->prepare('SELECT id, role FROM users WHERE email = ? FOR UPDATE');
                $st->execute([$new_email]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $userId = (int)$row['id'];
                    if ($row['role'] !== 'admin') {
                        $pdo->prepare('UPDATE users SET role="admin" WHERE id=?')->execute([$userId]);
                    }
                } else {
                    // Generar username base y asegurar unicidad con sufijos si es necesario
                    $base = preg_replace('/[^a-z0-9_]+/i','', substr(strtolower(explode('@',$new_email)[0]),0,25));
                    if ($base === '') { $base = 'admin'; }
                    $username = $base;
                    $suffix = 1;
                    $maxAttempts = 15;
                    $existsStmt = $pdo->prepare('SELECT 1 FROM users WHERE username=? LIMIT 1');
                    while ($suffix <= $maxAttempts) {
                        $existsStmt->execute([$username]);
                        if (!$existsStmt->fetchColumn()) { break; }
                        $username = $base . '_' . $suffix;
                        $suffix++;
                    }
                    if ($suffix > $maxAttempts) {
                        throw new Exception('No se pudo generar un username único tras varios intentos');
                    }
                    $hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost'=>10]);
                    $ins = $pdo->prepare('INSERT INTO users (username,email,password,first_name,last_name,role) VALUES (?,?,?,?,?,"admin")');
                    $ins->execute([$username,$new_email,$hash,$new_first,$new_last]);
                    $userId = (int)$pdo->lastInsertId();
                }

                // Relación en tabla de administradores
                $ck = $pdo->prepare("SELECT id FROM `{$ADMIN_TABLE}` WHERE user_id=? FOR UPDATE");
                $ck->execute([$userId]);
                if (!$ck->fetch(PDO::FETCH_ASSOC)) {
                    $add = $pdo->prepare("INSERT INTO `{$ADMIN_TABLE}` (user_id, company_name) VALUES (?, ?)");
                    $add->execute([$userId, $new_company]);
                } elseif ($new_company !== '') {
                    $pdo->prepare("UPDATE `{$ADMIN_TABLE}` SET company_name=? WHERE user_id=?")->execute([$new_company,$userId]);
                }

                if ($pdo->inTransaction()) { $pdo->commit(); }
                $success = 'Nuevo administrador creado/actualizado correctamente.';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                // Logging detallado
                try {
                    $logDir = __DIR__ . '/../var';
                    if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
                    @file_put_contents($logDir . '/admin_create_errors.log', date('c') . ' :: ' . $e->getMessage() . "\n", FILE_APPEND);
                } catch (Exception $ignore) {}
                $error = 'No se pudo crear el nuevo administrador: ' . htmlspecialchars($e->getMessage());
            }
        }
    } else {
        // Update profile and optional password
        $company_name = trim($_POST['company_name'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($company_name) || empty($first_name) || empty($last_name) || empty($email)) {
            $error = 'Por favor complete todos los campos obligatorios';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Por favor ingrese un correo electrónico válido';
        } else {
            $pdo->beginTransaction();
            try {
                if ($admin['user_id']) {
                    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$first_name, $last_name, $email, $admin['user_id']]);
                }
                if ($admin_id) {
                    $stmt = $pdo->prepare("UPDATE `{$ADMIN_TABLE}` SET company_name = ? WHERE id = ?");
                    $stmt->execute([$company_name, $admin_id]);
                }
                if (!empty($new_password)) {
                    if (empty($current_password)) {
                        $error = 'Por favor ingrese su contraseña actual para cambiarla';
                    } elseif (strlen($new_password) < 6) {
                        $error = 'La nueva contraseña debe tener al menos 6 caracteres';
                    } elseif ($new_password !== $confirm_password) {
                        $error = 'Las contraseñas nuevas no coinciden';
                    } else {
                        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                        $stmt->execute([$admin['user_id']]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!$user || !password_verify($current_password, $user['password'])) {
                            $error = 'La contraseña actual es incorrecta';
                        } else {
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $stmt->execute([$hashed_password, $admin['user_id']]);
                            $success = 'Información actualizada exitosamente';
                        }
                    }
                } else {
                    $success = 'Información actualizada exitosamente';
                }

                if ($pdo->inTransaction()) {
                    if (empty($error)) { $pdo->commit(); } else { $pdo->rollBack(); }
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $error = 'Error al actualizar la información. Por favor intente nuevamente.';
            }
        }
    }

    // Refresh admin info after any POST action
    try {
        $fresh = fetchAdminRecord($pdo, $ADMIN_TABLE, $admin_id);
        if ($fresh !== null) { $admin = $fresh; $adminsBroken = false; }
    } catch (PDOException $e) {
        $adminsBroken = true;
    }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Rotteri Nza Kus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/modern.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .admin-header h1 {
            margin: 0;
            font-size: 2rem;
        }
        
        /* Admin nav menu styles intentionally removed to use default browser styles */
        
        .admin-content {
            background: linear-gradient(180deg, rgba(28,37,65,.95), rgba(28,37,65,.85));
            color: #e5e7eb;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,.25);
            border: 1px solid rgba(255,255,255,.06);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid rgba(255,255,255,.14);
            border-radius: 8px;
            font-size: 1rem;
            background: rgba(255,255,255,.06);
            color: #e5e7eb;
        }
        .form-group input::placeholder,
        .form-group textarea::placeholder { color: #9aa4b2; }
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus { outline: none; border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(59,130,246,.25); }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn-primary {
            background: #9b59b6;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .btn-primary:hover {
            background: #8e44ad;
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .password-section {
            background: linear-gradient(180deg, rgba(28,37,65,.96), rgba(28,37,65,.88));
            color: #e5e7eb;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,.25);
            border: 1px solid rgba(255,255,255,.08);
        }
        
        .password-section h3 {
            margin-top: 0;
            color: #e5e7eb;
            border-bottom: 2px solid rgba(255,255,255,.12);
            padding-bottom: 10px;
        }
        /* Alerts (in case global styles don't cover) */
        .alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 12px; border: 1px solid transparent; color:#e5e7eb; }
        .alert-success { background: rgba(34,197,94,.18); border-color: rgba(34,197,94,.25); }
        .alert-danger { background: rgba(244,63,94,.18); border-color: rgba(244,63,94,.25); }
        .alert-warning { background: rgba(250,204,21,.18); border-color: rgba(250,204,21,.25); color: #fde68a; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/layout_header.php'; ?>

    <div class="admin-container">
        <div class="admin-header">
            <h1>Configuración</h1>
            <p>Bienvenido, <?php echo htmlspecialchars(trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?: ($_SESSION['user_email'] ?? 'Admin')); ?></p>
        </div>
        
        <?php include __DIR__ . '/../includes/admin_navbar.php'; ?>
        
    <div class="admin-content">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($adminsBroken): ?>
                <div id="adminsRepairBox" class="alert alert-danger">
                    <strong>Tabla <?php echo htmlspecialchars($ADMIN_TABLE); ?> dañada o inexistente.</strong>
                    <div style="margin-top:6px;font-size:0.95rem;opacity:.9">
                        El sistema detectó un problema al acceder a <code><?php echo htmlspecialchars($ADMIN_TABLE); ?></code>.
                        <?php if (!empty($_SESSION['auto_repair_attempt_admins'])): ?>
                            Ya se intentó una autoreparación sin éxito.
                        <?php else: ?>
                            (Se intentará una autoreparación silenciosa al recargar.)
                        <?php endif; ?>
                        Puedes intentar repararla manualmente con el botón. Si el problema persiste puede tratarse de un error InnoDB (código 1813/1932).
                        <?php if ($ADMIN_TABLE !== 'admins'): ?>
                            <div style="margin-top:4px;font-size:0.8rem;opacity:.85">Nota: Se está usando un nombre alternativo de tabla de administradores (<code><?php echo htmlspecialchars($ADMIN_TABLE); ?></code>) porque la tabla principal <code>admins</code> está corrupta/inaccesible.</div>
                        <?php endif; ?>
                    </div>
                    <details style="margin-top:8px;">
                        <summary style="cursor:pointer;">Ver pasos manuales detallados (Windows/XAMPP)</summary>
                        <ol style="margin-top:6px; padding-left:18px; font-size:0.85rem; line-height:1.35;">
                            <li><strong>Respalda</strong> tu base de datos: phpMyAdmin &gt; Exportar &gt; Rápido (SQL).</li>
                            <li>Identifica el nombre exacto de la base (ej: <code>rotteri_nza_kus</code>).</li>
                            <li>Detén MySQL en XAMPP (Panel de Control &gt; botón Stop en MySQL).</li>
                            <li>Navega a <code>C:/xampp/mysql/data/NOMBRE_BASE/</code>.</li>
                            <li>Mueve (no borres todavía) los archivos <code>admins.ibd</code> y si existe <code>admins.frm</code> a una carpeta respaldo.</li>
                            <li>Inicia de nuevo MySQL.</li>
                            <li>En phpMyAdmin ejecuta: <code>DROP TABLE IF EXISTS admins;</code> (ignora error si no existe).</li>
                            <li>Crea la tabla ejecutando:
                                <pre style="background:rgba(0,0,0,.35);padding:6px;border:1px solid rgba(255,255,255,.15);border-radius:6px;overflow:auto;">CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  company_name VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</pre>
                            </li>
                            <li>Recarga esta página. Crea el perfil si aún no existe.</li>
                            <li><em>Opcional avanzado:</em> Si el error 1813 persiste revisa <code>SHOW ENGINE INNODB STATUS;</code> y considera (temporalmente) <code>innodb_force_recovery=1</code> en <code>my.ini</code> sólo para extraer datos, luego vuelve a 0.</li>
                        </ol>
                        <div style="font-size:0.75rem;opacity:.7;">Códigos 1813/1932 indican desincronización entre diccionario InnoDB y archivos físicos (.ibd). La recreación limpia suele resolver.</div>
                    </details>
                    <button id="retryAdminsRepair" type="button" class="btn btn-primary" style="margin-top:10px">Reparar tabla <?php echo htmlspecialchars($ADMIN_TABLE); ?></button>
                    <button id="diagnoseAdmins" type="button" class="btn btn-primary" style="margin-top:10px;background:#2563eb">Diagnóstico rápido</button>
                    <div id="adminsRepairStatus" style="margin-top:8px;font-size:0.85rem"></div>
                </div>
            <?php endif; ?>

            <?php if (!$adminsBroken && !$hasAdminProfile): ?>
                <div class="alert alert-warning">No tienes un perfil de administrador creado aún.</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="create_admin_profile" value="1">
                    <button type="submit" class="btn btn-primary">Crear perfil de administrador</button>
                </form>
                <hr>
            <?php endif; ?>

            <?php if (!$adminsBroken): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <div class="form-group">
                    <label for="company_name">Nombre de la Empresa</label>
                    <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($admin['company_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="first_name">Nombre</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($admin['first_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Apellido</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($admin['last_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Correo Electrónico</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </form>
            <?php else: ?>
                <div class="alert alert-warning">No se puede editar el perfil mientras la tabla <code><?php echo htmlspecialchars($ADMIN_TABLE); ?></code> no esté reparada.</div>
            <?php endif; ?>
            
            <?php if (!$adminsBroken): ?>
            <div class="password-section">
                <h3>Cambiar Contraseña</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <div class="form-group">
                        <label for="current_password">Contraseña Actual</label>
                        <input type="password" id="current_password" name="current_password">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Nueva Contraseña</label>
                        <input type="password" id="new_password" name="new_password">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirmar Nueva Contraseña</label>
                        <input type="password" id="confirm_password" name="confirm_password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-key"></i> Cambiar Contraseña
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <?php if (!$adminsBroken): ?>
            <div class="password-section" style="margin-top:25px;">
                <h3>Crear Nuevo Administrador</h3>
                <p style="margin-top:0;font-size:.9rem;opacity:.85">Si el correo ya existe, la cuenta será elevada a administrador. La contraseña solo se usará si la cuenta no existe todavía.</p>
                <form method="POST" onsubmit="return confirm('¿Crear o actualizar este administrador?');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="create_new_admin_user" value="1">
                    <div class="form-group">
                        <label>Correo</label>
                        <input type="email" name="new_admin_email" required placeholder="email@dominio.com">
                    </div>
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" name="new_admin_password" required placeholder="Mínimo 8 caracteres">
                    </div>
                    <div class="form-group" style="display:flex; gap:12px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:160px;">
                            <label>Nombre</label>
                            <input type="text" name="new_admin_first_name" required>
                        </div>
                        <div style="flex:1; min-width:160px;">
                            <label>Apellido</label>
                            <input type="text" name="new_admin_last_name" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Empresa (opcional)</label>
                        <input type="text" name="new_admin_company" placeholder="Nombre de la empresa">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Crear administrador</button>
                </form>
            </div>
            <?php endif; ?>

            <div class="password-section" style="margin-top:20px;">
                <h3>Mantenimiento</h3>
                <p>Elimina imágenes en <code>uploads/</code> que no estén asociadas a ningún producto.</p>
                <form method="POST" onsubmit="return confirm('¿Seguro que deseas limpiar imágenes huérfanas? Esta acción no se puede deshacer.');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                    <input type="hidden" name="cleanup_orphan_uploads" value="1">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-broom"></i> Limpiar imágenes huérfanas
                    </button>
                </form>
            </div>
        </div>
    </div>
    <script>
    // Scripts de diagnóstico/reparación eliminados junto con endpoints inseguros.
    </script>
</body>
</html>