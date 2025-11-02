<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

requireAdmin();

// Defensive: ensure categories table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // ignore
}

$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Categorías - Panel Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/modern.css">
  <link rel="stylesheet" href="../css/toast.css">
  <style>
    .admin-container{max-width:1200px;margin:0 auto;padding:20px}
    .admin-header{background:#2c3e50;color:#fff;padding:20px;border-radius:10px;margin-bottom:20px}
  /* Admin nav menu styles intentionally removed to use default browser styles */
  .admin-content{background:linear-gradient(180deg, rgba(28,37,65,.95), rgba(28,37,65,.85));color:#e5e7eb;padding:20px;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.25);border:1px solid rgba(255,255,255,.06)}
  .row{display:flex;gap:20px;flex-wrap:wrap}
  .card{flex:1;min-width:320px;border:1px solid rgba(255,255,255,.08);border-radius:10px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.25);background:linear-gradient(180deg, rgba(28,37,65,.95), rgba(28,37,65,.85));color:#e5e7eb}
  .card h3{margin:0;padding:15px 20px;background:rgba(255,255,255,.06);border-bottom:1px solid rgba(255,255,255,.08);color:#e5e7eb}
  .card-body{padding:20px;color:#cbd5e1}
  .form-group{margin-bottom:12px}
  .form-group label{display:block;margin-bottom:6px;font-weight:600;color:#e5e7eb}
  .form-group input{width:100%;padding:10px;border:1px solid rgba(255,255,255,.2);border-radius:6px;background:rgba(255,255,255,.06);color:#e5e7eb}
    .btn{padding:8px 14px;border:none;border-radius:6px;cursor:pointer;font-weight:600}
    .btn-primary{background:#9b59b6;color:#fff}
    .btn-danger{background:#e74c3c;color:#fff}
    .btn-secondary{background:#3498db;color:#fff}
  table{width:100%;border-collapse:collapse;color:#e5e7eb}
  th,td{padding:10px;border-bottom:1px solid rgba(255,255,255,.12);text-align:left}
  th{background:rgba(255,255,255,.06)}
  tr:hover{background:rgba(255,255,255,.04)}
    .actions{display:flex;gap:8px}
  </style>
</head>
<body>
  <?php include __DIR__ . '/../includes/layout_header.php'; ?>

  <div class="admin-container">
    <div class="admin-header">
      <h1>Gestión de Categorías</h1>
      <p>Administra las categorías de productos.</p>
    </div>

    <?php include __DIR__ . '/../includes/admin_navbar.php'; ?>

    <div class="admin-content">
      <div class="row">
        <div class="card">
          <h3>Nueva categoría</h3>
          <div class="card-body">
            <form id="createCategory">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
              <div class="form-group">
                <label>Nombre</label>
                <input type="text" name="name" required placeholder="Ej: Bebidas">
              </div>
              <button class="btn btn-primary" type="submit"><i class="fas fa-plus"></i> Crear</button>
            </form>
          </div>
        </div>

        <div class="card" style="flex:2">
          <h3>Listado de categorías</h3>
          <div class="card-body">
            <table>
              <thead>
                <tr><th>Nombre</th><th>Creada</th><th>Acciones</th></tr>
              </thead>
              <tbody id="catTableBody">
                <?php foreach ($categories as $c): ?>
                <tr data-id="<?php echo (int)$c['id']; ?>">
                  <td>
                    <input type="text" class="cat-name" value="<?php echo htmlspecialchars($c['name']); ?>" />
                  </td>
                  <td><?php echo htmlspecialchars($c['created_at']); ?></td>
                  <td class="actions">
                    <button class="btn btn-secondary btn-rename"><i class="fas fa-save"></i> Guardar</button>
                    <button class="btn btn-danger btn-delete"><i class="fas fa-trash"></i> Eliminar</button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Create category
    document.getElementById('createCategory').addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
  if (!fd.get('csrf_token')) fd.append('csrf_token', '<?php echo htmlspecialchars(csrf_token()); ?>');
  const res = await fetch('category_create.php', { method: 'POST', body: fd });
      const data = await res.json();
  if (!data.success) return toast.error(data.message || 'Error');
      location.reload();
    });

    // Rename & Delete actions
    document.querySelectorAll('#catTableBody tr').forEach((row) => {
      const id = row.getAttribute('data-id');
      const input = row.querySelector('.cat-name');
      row.querySelector('.btn-rename').addEventListener('click', async () => {
  const fd = new FormData();
  fd.append('csrf_token', '<?php echo htmlspecialchars(csrf_token()); ?>');
        fd.append('id', id);
        fd.append('name', input.value.trim());
        const res = await fetch('category_rename.php', { method: 'POST', body: fd });
        const data = await res.json();
  if (!data.success) return toast.error(data.message || 'Error');
  toast.success('Guardado');
      });
      row.querySelector('.btn-delete').addEventListener('click', async () => {
        if (!confirm('¿Eliminar esta categoría?\nNota: si hay productos vinculados, deberás reasignarlos.')) return;
  const fd = new FormData();
  fd.append('csrf_token', '<?php echo htmlspecialchars(csrf_token()); ?>');
        fd.append('id', id);
        const res = await fetch('category_delete.php', { method: 'POST', body: fd });
        const data = await res.json();
  if (!data.success) return toast.error(data.message || 'Error');
        row.remove();
      });
    });
  </script>
  
</body>
</html>
<script src="../js/toast.js"></script>
