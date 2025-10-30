<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Solo super administradores pueden acceder a esta página
requireSuperAdmin();

$success = '';
$error = '';

// Asegurar que las tablas y columnas necesarias existan
createAdminIfNotExists($pdo);
ensureUserPermissionsTable($pdo);

// Catálogo de permisos disponibles
$AVAILABLE_PERMS = [
    'access_caja' => 'Acceso a Caja',
    'access_expenses' => 'Ver página de Gastos',
    'export_csv' => 'Exportar CSV/Reportes',
    'clear_withdrawals' => 'Limpiar historial de retiros',
    'view_ingresos_totales' => 'Ver Ingresos Totales',
    'view_beneficios_totales' => 'Ver Beneficios Totales',
    'view_envios_entregados' => 'Ver Envíos Entregados',
    'view_kilos_entregados' => 'Ver Kilos Entregados',
    'view_gastos_totales' => 'Ver Gastos Totales',
    'view_beneficio_neto' => 'Ver Beneficio Neto',
    'view_saldo_real_disponible' => 'Ver Saldo Real Disponible',
];

// Procesamiento de formularios POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if (in_array($action, ['create', 'save_modal_changes', 'delete_user'])) {
            $pdo->beginTransaction();
        }

        switch ($action) {
            case 'create':
                $partner_name = trim($_POST['partner_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'user';
                if (empty($partner_name) || empty($email) || empty($password)) {
                    throw new Exception('Nombre, email y contraseña son obligatorios.');
                }

                $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    throw new Exception('El correo ya está registrado.');
                }

                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (email, password, role, partner_name) VALUES (?, ?, ?, ?)');
                $stmt->execute([$email, $hashed, $role, $partner_name]);
                $newUserId = (int)$pdo->lastInsertId();

                $perms = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];
                setUserPermissions($pdo, $newUserId, $perms);
                $success = 'Socio y credenciales creados correctamente.';
                break;

            case 'save_modal_changes':
                $user_id = (int)($_POST['user_id'] ?? 0);
                if ($user_id <= 0) throw new Exception('ID de usuario inválido.');

                $role = $_POST['role'] ?? 'user';
                $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE id = ?');
                $stmt->execute([$role, $user_id]);

                $partner_name = trim($_POST['partner_name'] ?? '');
                $stmt = $pdo->prepare('UPDATE users SET partner_name = ? WHERE id = ?');
                $stmt->execute([$partner_name ?: null, $user_id]);

                $password = $_POST['password'] ?? '';
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                    $stmt->execute([$hashed, $user_id]);
                }

                $perms = isset($_POST['permissions']) && is_array($_POST['permissions']) ? $_POST['permissions'] : [];
                setUserPermissions($pdo, $user_id, $perms);
                $success = 'Cambios guardados para el usuario ID ' . $user_id;
                break;

            case 'delete_user':
                $user_id = (int)($_POST['user_id'] ?? 0);
                if ($user_id <= 0) throw new Exception('ID de usuario inválido.');
                if ($user_id === (int)($_SESSION['user_id'] ?? 0)) throw new Exception('No puedes eliminar tu propia cuenta.');

                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$user_id]);

                $stmt = $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ?');
                $stmt->execute([$user_id]);
                $success = 'Usuario ID ' . $user_id . ' ha sido eliminado.';
                break;
        }

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Error: ' . $e->getMessage();
    }
    header("Location: register_partner.php?success=" . urlencode($success) . "&error=" . urlencode($error));
    exit();
}

$users = $pdo->query("SELECT id, email, role, partner_name FROM users ORDER BY FIELD(role, 'super_admin', 'admin', 'user'), COALESCE(partner_name, ''), email ASC")->fetchAll(PDO::FETCH_ASSOC);

$partners = $pdo->query('SELECT partner_name FROM partner_benefits ORDER BY partner_name ASC')->fetchAll(PDO::FETCH_COLUMN);

foreach ($users as $key => $user) {
    // DEBUG: Temporarily disable permission fetching to isolate the display issue.
    $users[$key]['permissions'] = [];
    // $users[$key]['permissions'] = getUserPermissions($pdo, (int)$user['id']);
}

$success_msg = $_GET['success'] ?? '';
$error_msg = $_GET['error'] ?? '';

include 'includes/header.php';
?>
<link rel="stylesheet" href="css/admin-register-partner.css">

<div class="container py-4 admin-console-modern">
    <h2 class="mb-4"><i class="fas fa-users-cog me-2"></i>Gestión de Usuarios y Permisos</h2>

    <?php if ($success_msg): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo htmlspecialchars(urldecode($success_msg)); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>
    <?php if ($error_msg): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo htmlspecialchars(urldecode($error_msg)); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div><?php endif; ?>

    <ul class="nav nav-tabs mb-3" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-panel" type="button" role="tab" aria-controls="users-panel" aria-selected="true"><i class="fas fa-users me-1"></i>Usuarios</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="add-user-tab" data-bs-toggle="tab" data-bs-target="#add-user-panel" type="button" role="tab" aria-controls="add-user-panel" aria-selected="false"><i class="fas fa-user-plus me-1"></i>Añadir Nuevo</button></li>
    </ul>

    <div class="tab-content" id="adminTabsContent">
        <div class="tab-pane fade show active" id="users-panel" role="tabpanel" aria-labelledby="users-tab">
            <?php if (empty($users)): ?>
                <div class="text-center p-5 border rounded"><p class="mb-0">No hay usuarios registrados.</p></div>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                <div class="user-card role-<?php echo strtolower($user['role']); ?>">
                    <div class="user-info">
                        <div class="user-avatar bg-<?php echo strtolower($user['role']); ?>"><?php echo strtoupper(substr($user['email'], 0, 1)); ?></div>
                        <div class="user-details">
                            <p class="email"><?php echo htmlspecialchars($user['email']); ?></p>
                            <p class="partner-name"><?php echo htmlspecialchars($user['partner_name'] ?? 'Sin socio asignado'); ?></p>
                        </div>
                    </div>
                    <div class="user-actions d-flex align-items-center gap-3">
                        <span class="badge fs-6"><?php echo htmlspecialchars($user['role']); ?></span>
                        <button class="btn btn-primary btn-sm manage-user-btn" data-bs-toggle="modal" data-bs-target="#manageUserModal" data-user-id="<?php echo $user['id']; ?>"><i class="fas fa-edit"></i> Gestionar</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="add-user-panel" role="tabpanel" aria-labelledby="add-user-tab">
            <form method="post"><input type="hidden" name="action" value="create">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Nombre del Socio</label><input type="text" name="partner_name" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Rol</label><select name="role" class="form-select" required><option value="user">Socio (user)</option><option value="admin">Administrador</option></select></div>
                    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Contraseña</label><input type="password" name="password" class="form-control" required></div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Permisos</label>
                        <div class="permissions-grid">
                            <?php foreach ($AVAILABLE_PERMS as $key => $label): ?><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($key); ?>" id="perm_new_<?php echo htmlspecialchars($key); ?>"><label class="form-check-label" for="perm_new_<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></label></div><?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-12"><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Registrar Usuario</button></div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="manageUserModal" tabindex="-1" aria-labelledby="manageUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manageUserModalLabel">Gestionar Usuario: <span id="modal_user_email"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" id="modal-main-form">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save_modal_changes">
                    <input type="hidden" name="user_id" id="modal_user_id_main">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <h5><i class="fas fa-user-tag me-2"></i>Rol y Socio</h5>
                            <label class="form-label">Rol</label>
                            <select name="role" id="modal_role" class="form-select mb-3"><option value="user">Socio (user)</option><option value="admin">Administrador</option></select>
                            <label class="form-label">Asignar Socio</label>
                            <select name="partner_name" id="modal_partner_name" class="form-select"><option value="">(Sin socio)</option>
                                <?php foreach ($partners as $p): ?><option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <h5><i class="fas fa-key me-2"></i>Resetear Contraseña</h5>
                            <label class="form-label">Nueva Contraseña</label>
                            <input type="password" name="password" class="form-control" placeholder="Dejar en blanco para no cambiar">
                        </div>
                        <div class="col-12">
                            <h5><i class="fas fa-check-circle me-2"></i>Permisos</h5>
                            <div class="permissions-grid" id="modal_permissions">
                                <?php foreach ($AVAILABLE_PERMS as $key => $label): ?><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($key); ?>" id="perm_modal_<?php echo htmlspecialchars($key); ?>"><label class="form-check-label" for="perm_modal_<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></label></div><?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="submit" form="modal-delete-form" class="btn btn-danger"><i class="fas fa-trash-alt me-1"></i>Eliminar Usuario</button>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="submit" form="modal-main-form" class="btn btn-primary"><i class="fas fa-save me-1"></i>Guardar Cambios</button>
                    </div>
                </div>
            </form>
            <form method="post" id="modal-delete-form" onsubmit="return confirm('¿Estás seguro de que quieres eliminar a este usuario? Esta acción no se puede deshacer.');">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="modal_user_id_delete">
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof bootstrap === 'undefined') { console.error('Bootstrap JS no está cargado.'); return; }

    const manageUserModalEl = document.getElementById('manageUserModal');
    if (!manageUserModalEl) return;

    const manageUserModal = new bootstrap.Modal(manageUserModalEl);
    const usersData = <?php echo json_encode($users, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    document.querySelectorAll('.manage-user-btn').forEach(button => {
        button.addEventListener('click', function () {
            const userId = this.dataset.userId;
            const userData = usersData.find(u => u.id == userId);
            if (userData) {
                document.getElementById('modal_user_id_main').value = userData.id;
                document.getElementById('modal_user_id_delete').value = userData.id;
                document.getElementById('modal_user_email').textContent = userData.email;
                document.getElementById('modal_role').value = userData.role;
                document.getElementById('modal_partner_name').value = userData.partner_name || '';
                document.getElementById('modal-main-form').querySelector('input[name="password"]').value = '';

                const permCheckboxes = document.querySelectorAll('#modal_permissions .form-check-input');
                permCheckboxes.forEach(cb => cb.checked = false);

                if (Array.isArray(userData.permissions)) {
                    userData.permissions.forEach(permKey => {
                        const checkbox = document.querySelector(`#modal_permissions input[value="${permKey}"]`);
                        if (checkbox) checkbox.checked = true;
                    });
                }
                manageUserModal.show();
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
