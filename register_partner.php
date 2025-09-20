<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Solo administradores pueden registrar socios y crear sus credenciales
requireSuperAdmin();

$success = '';
$error = '';

// Ensure users table has partner_name and role columns
createAdminIfNotExists($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Handle different admin actions
  if (isset($_POST['action']) && $_POST['action'] === 'test_login') {
    $emailT = trim($_POST['email'] ?? '');
    $passT = (string)($_POST['password'] ?? '');
    if ($emailT === '' || $passT === '') { $error = 'Email y contraseña requeridos para probar'; }
    else {
      try {
        // Check user row first for clearer diagnostics
        $stmt = $pdo->prepare('SELECT id, email, password, role, partner_name, COALESCE(active,1) AS active FROM users WHERE email = ?');
        $stmt->execute([$emailT]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$u) { $error = 'No existe usuario con ese email'; }
        else if ((string)$u['active'] === '0') { $error = 'La cuenta está inactiva (active=0)'; }
        else if (!password_verify($passT, $u['password'])) { $error = 'Contraseña incorrecta'; }
        else { $success = 'Credenciales válidas. Rol: ' . htmlspecialchars($u['role'] ?? 'user') . '; Socio: ' . htmlspecialchars($u['partner_name'] ?? ''); }
      } catch (Exception $e) { $error = 'Error al probar login: ' . $e->getMessage(); }
    }
  }
  elseif (isset($_POST['action']) && $_POST['action'] === 'create') {
    $partner_name = trim($_POST['partner_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'user';

    if ($partner_name === '' || $email === '' || $password === '') {
      $error = 'Todos los campos son obligatorios';
    } else {
      try {
        // Ensure partner exists in partner_benefits (DDL must be outside of a transaction)
        $pdo->exec("CREATE TABLE IF NOT EXISTS partner_benefits (
          id INT AUTO_INCREMENT PRIMARY KEY,
          partner_name VARCHAR(100) NOT NULL UNIQUE,
          percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
          total_earnings DECIMAL(15,2) NOT NULL DEFAULT 0.00,
          current_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
          wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
          last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        $stmt = $pdo->prepare('INSERT IGNORE INTO partner_benefits (partner_name, percentage) VALUES (?, 0.00)');
        $stmt->execute([$partner_name]);

        // Transaction for creating user
        $pdo->beginTransaction();
        // Create user with role and partner_name
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
          throw new Exception('El correo ya está registrado');
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (email, password, role, partner_name) VALUES (?, ?, ?, ?)');
        $stmt->execute([$email, $hashed, $role, $partner_name]);

  // Guardar permisos si vienen del formulario
  $newUserId = (int)$pdo->lastInsertId();
  $perms = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];
  setUserPermissions($pdo, $newUserId, $perms);

  if ($pdo->inTransaction()) { $pdo->commit(); }
  $success = 'Socio y credenciales creados correctamente';
      } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = 'Error: ' . $e->getMessage();
      }
    }
  } elseif (isset($_POST['action']) && $_POST['action'] === 'update_role') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $role = $_POST['role'] ?? 'user';
    if ($user_id <= 0) { $error = 'Usuario inválido'; }
    else {
      $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
      $stmt->execute([$role, $user_id]);
      $success = 'Rol actualizado';
    }
  } elseif (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    if ($user_id <= 0 || $password === '') { $error = 'Datos inválidos'; }
    else {
      $hashed = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
      $stmt->execute([$hashed, $user_id]);
      $success = 'Contraseña actualizada';
    }
  } elseif (isset($_POST['action']) && $_POST['action'] === 'update_partner') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $partner_name = trim($_POST['partner_name'] ?? '');
    if ($user_id <= 0) { $error = 'Usuario inválido'; }
    else {
      // Ensure exists in partner_benefits
      $pdo->exec("CREATE TABLE IF NOT EXISTS partner_benefits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_name VARCHAR(100) NOT NULL UNIQUE,
        percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        total_earnings DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        current_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )");
      if ($partner_name !== '') {
        $stmt = $pdo->prepare('INSERT IGNORE INTO partner_benefits (partner_name, percentage) VALUES (?, 0.00)');
        $stmt->execute([$partner_name]);
      }
      $stmt = $pdo->prepare('UPDATE users SET partner_name = ? WHERE id = ?');
      $stmt->execute([$partner_name !== '' ? $partner_name : null, $user_id]);
      $success = 'Socio asignado actualizado';
    }
  } elseif (isset($_POST['action']) && $_POST['action'] === 'revoke_admins') {
    $currentId = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $pdo->prepare('UPDATE users SET role = "user" WHERE role = "admin" AND id <> ?');
    $stmt->execute([$currentId]);
    $success = 'Se han revocado los permisos de administrador de todos (excepto el usuario actual).';
  } elseif (isset($_POST['action']) && $_POST['action'] === 'revoke_admins_all') {
    // Revocar incluso al usuario actual: perderás acceso de admin tras esta acción
    $stmt = $pdo->prepare('UPDATE users SET role = "user" WHERE role IN ("admin","super_admin")');
    $stmt->execute();
    $success = 'Se han revocado los permisos de administrador de todos los usuarios.';
  } elseif (isset($_POST['action']) && $_POST['action'] === 'purge_users_except_me') {
    $currentId = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM users WHERE id <> ?');
    $stmt->execute([$currentId]);
    $success = 'Todos los usuarios fueron eliminados (excepto el actual).';
  } elseif (isset($_POST['action']) && $_POST['action'] === 'purge_users_all') {
    // Eliminar absolutamente todos los usuarios (incluyéndote)
    $pdo->exec('DELETE FROM users');
    $success = 'Todos los usuarios han sido eliminados.';
  } elseif (isset($_POST['action']) && $_POST['action'] === 'create_super_admin') {
    // Only super_admins can create/overwrite super_admin user
    if (($_SESSION['role'] ?? 'user') !== 'super_admin') {
      $error = 'Solo super administradores pueden crear super admin';
    } else {
      $email = trim($_POST['email'] ?? '');
      $password = $_POST['password'] ?? '';
      if ($email === '' || $password === '') {
        $error = 'Email y contraseña requeridos';
      } else {
        try {
          $pdo->beginTransaction();
          // Upsert-like behavior: if exists update, else insert
          $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
          $stmt->execute([$email]);
          $hashed = password_hash($password, PASSWORD_DEFAULT);
          if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stmt = $pdo->prepare('UPDATE users SET password = ?, role = "super_admin", partner_name = NULL WHERE id = ?');
            $stmt->execute([$hashed, $row['id']]);
          } else {
            $stmt = $pdo->prepare('INSERT INTO users (email, password, role, partner_name) VALUES (?, ?, "super_admin", NULL)');
            $stmt->execute([$email, $hashed]);
          }
          $pdo->commit();
          $success = 'Super admin creado/actualizado';
        } catch (Exception $e) {
          if ($pdo->inTransaction()) $pdo->rollBack();
          $error = 'Error: '.$e->getMessage();
        }
      }
    }
  } elseif (isset($_POST['action']) && $_POST['action'] === 'sync_partners') {
    // Sincronizar nombres de socios desde usuarios, pagos y transacciones al maestro partner_benefits
    try {
      // Asegurar tabla partner_benefits (DDL fuera de transacciones)
      $pdo->exec("CREATE TABLE IF NOT EXISTS partner_benefits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_name VARCHAR(100) NOT NULL UNIQUE,
        percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        total_earnings DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        current_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
      )");

      $pdo->beginTransaction();
      $names = [];
      $push = function($rows) use (&$names) {
        foreach ($rows as $n) {
          $n = trim((string)$n);
          if ($n !== '') { $names[$n] = true; }
        }
      };
      // users.partner_name
      try {
        $rows = $pdo->query("SELECT DISTINCT partner_name FROM users WHERE partner_name IS NOT NULL AND partner_name <> ''")->fetchAll(PDO::FETCH_COLUMN);
        $push($rows);
      } catch (Exception $e) { /* ignore */ }
      // partner_payments.partner_name
      try {
        $rows = $pdo->query("SELECT DISTINCT partner_name FROM partner_payments WHERE partner_name IS NOT NULL AND partner_name <> ''")->fetchAll(PDO::FETCH_COLUMN);
        $push($rows);
      } catch (Exception $e) { /* ignore */ }
      // partner_wallet_transactions.partner_name
      try {
        $rows = $pdo->query("SELECT DISTINCT partner_name FROM partner_wallet_transactions WHERE partner_name IS NOT NULL AND partner_name <> ''")->fetchAll(PDO::FETCH_COLUMN);
        $push($rows);
      } catch (Exception $e) { /* ignore */ }
      // benefit_history.partner_name
      try {
        $rows = $pdo->query("SELECT DISTINCT partner_name FROM benefit_history WHERE partner_name IS NOT NULL AND partner_name <> ''")->fetchAll(PDO::FETCH_COLUMN);
        $push($rows);
      } catch (Exception $e) { /* ignore */ }

      $inserted = 0;
      $ins = $pdo->prepare('INSERT IGNORE INTO partner_benefits (partner_name, percentage) VALUES (?, 0.00)');
      foreach (array_keys($names) as $n) {
        if ($ins->execute([$n])) { $inserted += ($ins->rowCount() > 0 ? 1 : 0); }
      }
  if ($pdo->inTransaction()) { $pdo->commit(); }
      $success = 'Sincronización completa. Nuevos socios añadidos: ' . (int)$inserted;
    } catch (Exception $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
      $error = 'Error al sincronizar socios: ' . $e->getMessage();
    }
  } elseif (isset($_POST['action']) && $_POST['action'] === 'rename_partner') {
    // Renombrar socio en todas las tablas relacionadas (si el nuevo nombre no existe)
    $old = trim($_POST['old_name'] ?? '');
    $new = trim($_POST['new_name'] ?? '');
    if ($old === '' || $new === '' || $old === $new) {
      $error = 'Nombres inválidos para renombrar';
    } else {
      try {
        $pdo->beginTransaction();
        // Validar existencia del viejo y no-existencia del nuevo en partner_benefits
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM partner_benefits WHERE partner_name = ?');
        $stmt->execute([$old]);
        if ((int)$stmt->fetchColumn() === 0) { throw new Exception('El socio a renombrar no existe'); }
        $stmt->execute([$new]);
        if ((int)$stmt->fetchColumn() > 0) { throw new Exception('El nuevo nombre ya existe. Merge no implementado'); }

        // Actualizar partner_benefits
        $pdo->prepare('UPDATE partner_benefits SET partner_name = ? WHERE partner_name = ?')->execute([$new, $old]);
        // Actualizar users
        $pdo->prepare('UPDATE users SET partner_name = ? WHERE partner_name = ?')->execute([$new, $old]);
        // Actualizar partner_payments
        try { $pdo->prepare('UPDATE partner_payments SET partner_name = ? WHERE partner_name = ?')->execute([$new, $old]); } catch (Exception $e) { /* ignore */ }
        // Actualizar partner_wallet_transactions
        try { $pdo->prepare('UPDATE partner_wallet_transactions SET partner_name = ? WHERE partner_name = ?')->execute([$new, $old]); } catch (Exception $e) { /* ignore */ }
        // Actualizar benefit_history
        try { $pdo->prepare('UPDATE benefit_history SET partner_name = ? WHERE partner_name = ?')->execute([$new, $old]); } catch (Exception $e) { /* ignore */ }

  if ($pdo->inTransaction()) { $pdo->commit(); }
        $success = 'Socio renombrado de "' . htmlspecialchars($old) . '" a "' . htmlspecialchars($new) . '"';
      } catch (Exception $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $error = 'Error al renombrar socio: ' . $e->getMessage();
      }
    }
  }
  // Guardar permisos para un usuario existente
  elseif (isset($_POST['action']) && $_POST['action'] === 'save_permissions') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $perms = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];
    if ($user_id <= 0) { $error = 'Usuario inválido'; }
    else {
      if (setUserPermissions($pdo, $user_id, $perms)) { $success = 'Permisos actualizados'; }
      else { $error = 'No se pudieron actualizar permisos'; }
    }
  }
}
?>
<?php include 'includes/header.php'; ?>
<div class="container py-4 admin-console">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <h2 class="mb-0"><i class="fas fa-user-plus me-2"></i>Registrar Socio y Credenciales</h2>
    <a href="#usuarios" class="btn btn-outline-light btn-sm"><i class="fas fa-users"></i> Ir a usuarios existentes</a>
  </div>
  <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header"><strong><i class="fas fa-id-card me-2"></i>Nuevo Socio</strong></div>
        <div class="card-body">
          <form method="post" class="row g-3">
            <input type="hidden" name="action" value="create" />
            <div class="col-12">
              <label class="form-label">Nombre del Socio</label>
              <input type="text" name="partner_name" class="form-control" required />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" required />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Contraseña</label>
              <input type="password" name="password" class="form-control" required />
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Rol</label>
              <select name="role" class="form-select" required>
                <option value="user">Socio (user)</option>
                <option value="admin">Administrador</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Permisos (granulares)</label>
              <div class="d-flex flex-wrap align-items-center gap-3">
                <?php
                  // Catálogo de permisos disponibles
                  $AVAILABLE_PERMS = [
                    'access_caja' => 'Acceso a Caja',
                    'access_expenses' => 'Ver página de Gastos',
                    'view_benefits_totals' => 'Ver totales de beneficios/saldos',
                    'export_csv' => 'Exportar CSV/Reportes',
                    'clear_withdrawals' => 'Limpiar historial de retiros',
                  ];
                ?>
                <?php foreach ($AVAILABLE_PERMS as $key => $label): ?>
                  <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($key); ?>" id="perm_create_<?php echo htmlspecialchars($key); ?>">
                    <label class="form-check-label small" for="perm_create_<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></label>
                  </div>
                <?php endforeach; ?>
              </div>
              <small class="text-muted d-block mt-1">Nota: los super administradores tienen todos los permisos automáticamente.</small>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Registrar</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <?php if (($_SESSION['role'] ?? 'user') === 'super_admin'): ?>
    <div class="col-12 col-lg-6">
      <div class="card shadow-sm mb-3">
        <div class="card-header"><strong><i class="fas fa-key me-2"></i>Probar credenciales</strong></div>
        <div class="card-body">
          <form method="post" class="row g-3">
            <input type="hidden" name="action" value="test_login" />
            <div class="col-12">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" placeholder="email a probar" required />
            </div>
            <div class="col-12">
              <label class="form-label">Contraseña</label>
              <input type="password" name="password" class="form-control" required />
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-outline-light"><i class="fas fa-sign-in-alt me-1"></i>Probar login</button>
            </div>
          </form>
        </div>
      </div>
      <div class="card shadow-sm">
        <div class="card-header"><strong><i class="fas fa-user-shield me-2"></i>Super Admin</strong></div>
        <div class="card-body">
          <form method="post" class="row g-3" onsubmit="return confirm('Actualizar/crear super admin con estas credenciales?');">
            <input type="hidden" name="action" value="create_super_admin" />
            <div class="col-12">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" placeholder="email del super admin" required />
            </div>
            <div class="col-12">
              <label class="form-label">Contraseña</label>
              <input type="password" name="password" class="form-control" required />
            </div>
            <div class="col-12 d-flex gap-2">
              <button type="submit" class="btn btn-danger"><i class="fas fa-save me-1"></i>Guardar Super Admin</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <?php
    // Load existing users and partners for management
  $users = $pdo->query("SELECT id, email, role, partner_name 
                        FROM users 
                        ORDER BY FIELD(role,'super_admin','admin','user'), 
                                 COALESCE(partner_name,''), 
                                 email ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $partners = $pdo->query('SELECT partner_name FROM partner_benefits ORDER BY partner_name ASC')->fetchAll(PDO::FETCH_COLUMN);
  ?>

  <div class="mt-3">
    <div class="card shadow-sm" id="usuarios">
      <div class="card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-users me-2"></i>Usuarios Existentes</strong>
        <div class="section-actions">
          <form method="post" onsubmit="return confirm('Esto revocará los permisos de administrador de todos los usuarios (excepto tú). ¿Continuar?');">
            <input type="hidden" name="action" value="revoke_admins" />
            <button class="btn btn-outline-light btn-sm" type="submit"><i class="fas fa-user-shield"></i> Revocar admins (excepto yo)</button>
          </form>
          <form method="post" onsubmit="return confirm('Atención: te revocarás a ti también y perderás acceso de administrador inmediatamente. ¿Seguro?');">
            <input type="hidden" name="action" value="revoke_admins_all" />
            <button class="btn btn-danger btn-sm" type="submit"><i class="fas fa-user-slash"></i> Revocar TODOS los admins</button>
          </form>
          <form method="post" onsubmit="return confirm('Eliminará todos los usuarios excepto el actual. ¿Deseas continuar?');">
            <input type="hidden" name="action" value="purge_users_except_me" />
            <button class="btn btn-outline-light btn-sm" type="submit"><i class="fas fa-users-slash"></i> Eliminar usuarios (excepto yo)</button>
          </form>
          <form method="post" onsubmit="return confirm('Esto eliminará TODOS los usuarios, incluyéndote. Perderás acceso. ¿Seguro definitivo?');">
            <input type="hidden" name="action" value="purge_users_all" />
            <button class="btn btn-danger btn-sm" type="submit"><i class="fas fa-trash"></i> Eliminar TODOS los usuarios</button>
          </form>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-striped table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Email</th>
              <th>Rol</th>
              <th>Socio</th>
              <th>Actualizar Rol</th>
              <th>Asignar Socio</th>
              <th>Reset Contraseña</th>
              <th>Permisos</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td><?php echo (int)$u['id']; ?></td>
              <td><?php echo htmlspecialchars($u['email']); ?></td>
              <td>
                <?php $rb = 'bg-secondary'; if (($u['role']??'')==='admin') { $rb='bg-primary'; } elseif (($u['role']??'')==='super_admin') { $rb='bg-danger'; } ?>
                <span class="badge <?php echo $rb; ?>"><?php echo htmlspecialchars($u['role']); ?></span>
              </td>
              <td><?php echo htmlspecialchars($u['partner_name'] ?? ''); ?></td>
              <td>
                <form method="post" class="d-flex gap-2 align-items-center">
                  <input type="hidden" name="action" value="update_role" />
                  <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>" />
                  <select name="role" class="form-select form-select-sm" style="min-width:140px;">
                    <option value="user" <?php echo $u['role']==='user'?'selected':''; ?>>user</option>
                    <option value="admin" <?php echo $u['role']==='admin'?'selected':''; ?>>admin</option>
                  </select>
                  <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-save"></i></button>
                </form>
              </td>
              <td>
                <form method="post" class="d-flex gap-2 align-items-center">
                  <input type="hidden" name="action" value="update_partner" />
                  <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>" />
                  <select name="partner_name" class="form-select form-select-sm" style="min-width:180px;">
                    <option value="">(Sin socio)</option>
                    <?php foreach ($partners as $p): ?>
                      <option value="<?php echo htmlspecialchars($p); ?>" <?php echo ($u['partner_name']??'')===$p?'selected':''; ?>><?php echo htmlspecialchars($p); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-outline-light btn-sm" type="submit"><i class="fas fa-save"></i></button>
                </form>
              </td>
              <td>
                <form method="post" class="d-flex align-items-center" style="gap:.5rem;">
                  <input type="hidden" name="action" value="reset_password" />
                  <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>" />
                  <div class="input-group input-group-sm" style="min-width:220px;">
                    <input type="password" name="password" class="form-control" placeholder="Nueva contraseña" required />
                    <button class="btn btn-warning" type="submit"><i class="fas fa-sync-alt"></i></button>
                  </div>
                </form>
              </td>
              <td class="text-wrap" style="min-width:260px;">
                <?php $userPerms = getUserPermissions($pdo, (int)$u['id']); ?>
                <form method="post" class="d-flex flex-column gap-2 js-permissions-form">
                  <input type="hidden" name="action" value="save_permissions" />
                  <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>" />
                  <div class="d-flex flex-wrap align-items-center gap-3">
                    <?php foreach ($AVAILABLE_PERMS as $key => $label): ?>
                      <label class="form-check form-switch m-0 d-flex align-items-center" for="perm_<?php echo (int)$u['id']; ?>_<?php echo htmlspecialchars($key); ?>">
                        <input class="form-check-input me-2 js-perm-switch" type="checkbox" id="perm_<?php echo (int)$u['id']; ?>_<?php echo htmlspecialchars($key); ?>" name="permissions[]" value="<?php echo htmlspecialchars($key); ?>" <?php echo in_array($key, $userPerms, true) ? 'checked' : ''; ?>>
                        <span class="small"><?php echo htmlspecialchars($label); ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                  <div class="mt-1">
                    <button class="btn btn-outline-light btn-sm" type="submit"><i class="fas fa-save me-1"></i>Guardar permisos</button>
                    <span class="perm-status small ms-2"></span>
                  </div>
                </form>
              </td>
            </tr>
            
            <?php endforeach; ?>

          </tbody>
                
        </table>

      </div>
    </div>
  </div>

  <?php if (($_SESSION['role'] ?? 'user') === 'super_admin'): ?>
  <div class="row g-3 mt-3">
    <div class="col-12">
      <div class="card shadow-sm">
  <div class="card-header"><strong><i class="fas fa-sync-alt me-2"></i>Sincronización de Socios</strong></div>
        <div class="card-body d-flex flex-wrap gap-2 align-items-end">
          <form method="post" onsubmit="return confirm('Sincronizará nombres desde usuarios/pagos/transacciones hacia la lista de socios. ¿Continuar?');">
            <input type="hidden" name="action" value="sync_partners" />
            <button class="btn btn-outline-light" type="submit"><i class="fas fa-sync-alt"></i> Sincronizar nombres</button>
          </form>
          <form method="post" onsubmit="return confirm('Renombrar socio en todas las tablas. Asegúrate de que el nuevo nombre no exista. ¿Proceder?');" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="rename_partner" />
            <div class="col-12 col-md-auto">
              <label class="form-label">Nombre actual</label>
              <input type="text" name="old_name" class="form-control" placeholder="Nombre actual" required />
            </div>
            <div class="col-12 col-md-auto">
              <label class="form-label">Nuevo nombre</label>
              <input type="text" name="new_name" class="form-control" placeholder="Nuevo nombre" required />
            </div>
            <div class="col-12 col-md-auto">
              <button class="btn btn-warning" type="submit"><i class="fas fa-i-cursor me-1"></i>Renombrar socio</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<script>
  (function(){
    function toFormData(form){
      const fd = new FormData(form);
      // If no permissions are checked, ensure we still send permissions[] as empty
      if(![...form.querySelectorAll('input[name="permissions[]"]:checked')].length){
        // nothing; server treats missing as empty already
      }
      return fd;
    }
    async function savePermissions(form){
      const status = form.querySelector('.perm-status');
      if(status){ status.textContent = 'Guardando…'; }
      try{
        const res = await fetch(window.location.href, { method: 'POST', body: toFormData(form) });
        // Best-effort; we won’t replace the page, only show status
        if(res.ok){
          if(status){ status.textContent = 'Guardado'; setTimeout(()=> status.textContent = '', 1500); }
        } else {
          if(status){ status.textContent = 'Error al guardar'; }
        }
      } catch(e){ if(status){ status.textContent = 'Error de red'; } }
    }
    document.addEventListener('change', (e)=>{
      const el = e.target;
      if(el && el.classList && el.classList.contains('js-perm-switch')){
        const form = el.closest('form.js-permissions-form');
        if(form){ savePermissions(form); }
      }
    });
  })();
</script>
<?php include 'includes/footer.php'; ?>
