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
  <link rel="stylesheet" href="../css/toast.css">
  <style>
    .admin-container{max-width:1200px;margin:0 auto;padding:20px}
    .admin-header{background:#2c3e50;color:#fff;padding:20px;border-radius:10px;margin-bottom:20px}
    .admin-nav{background:#34495e;padding:10px 20px;border-radius:5px;margin-bottom:20px}
    .admin-nav ul{list-style:none;display:flex;margin:0;padding:0}
    .admin-nav li{margin-right:20px}
    .admin-nav a{color:#fff;text-decoration:none;padding:10px 15px;border-radius:5px;transition:background .3s}
    .admin-nav a:hover,.admin-nav a.active{background:#3498db}
    .admin-content{background:#fff;padding:20px;border-radius:10px;box-shadow:0 5px 15px rgba(0,0,0,.1)}
    .row{display:flex;gap:20px;flex-wrap:wrap}
    .card{flex:1;min-width:320px;border:1px solid #ddd;border-radius:10px;overflow:hidden;box-shadow:0 3px 10px rgba(0,0,0,.08)}
    .card h3{margin:0;padding:15px 20px;background:#f7f8fa;border-bottom:1px solid #eee}
    .card-body{padding:20px}
    .form-group{margin-bottom:12px}
    .form-group label{display:block;margin-bottom:6px;font-weight:600}
    .form-group input{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px}
    .btn{padding:8px 14px;border:none;border-radius:6px;cursor:pointer;font-weight:600}
    .btn-primary{background:#9b59b6;color:#fff}
    .btn-danger{background:#e74c3c;color:#fff}
    .btn-secondary{background:#3498db;color:#fff}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px;border-bottom:1px solid #eee;text-align:left}
    tr:hover{background:#fafafa}
    .actions{display:flex;gap:8px}
  </style>
</head>
<body>
  <header class="header">
    <div class="container">
      <div class="header-content">
        <div class="logo">
          <img src="../img/logo-without-bg.png" alt="Rotteri Nza Kus Logo">
          <h1>Rotteri Nza Kus</h1>
        </div>
        <nav class="nav">
          <ul class="nav-menu">
            <li><a href="../index.php">Inicio</a></li>
            <li><a href="../index.php#products">Productos</a></li>
            <li><a href="../index.php#contact">Contacto</a></li>
            <?php if (isAuthenticated()): ?>
              <?php if (isAdmin()): ?>
                <li><a href="index.php">Panel Admin</a></li>
                <li><a href="categories.php" class="active">Categorías</a></li>
                <li><a href="orders.php">Pedidos</a></li>
                <li><a href="settings.php">Configuración</a></li>
                <li><a href="../profile.php">Mi Perfil</a></li>
              <?php else: ?>
                <li><a href="../profile.php">Mi Perfil</a></li>
              <?php endif; ?>
              <li><a href="../logout.php">Cerrar Sesión</a></li>
            <?php else: ?>
              <li><a href="../login.php">Iniciar Sesión</a></li>
              <li><a href="../register.php">Registrarse</a></li>
            <?php endif; ?>
          </ul>
        </nav>
        <div class="cart-icon"><i class="fas fa-shopping-cart"></i><span class="cart-count">0</span></div>
        <div class="menu-toggle"><i class="fas fa-bars"></i></div>
      </div>
    </div>
  </header>

  <div class="admin-container">
    <div class="admin-header">
      <h1>Gestión de Categorías</h1>
      <p>Administra las categorías de productos.</p>
    </div>

    <div class="admin-nav">
      <ul>
        <li><a href="index.php">Productos</a></li>
        <li><a href="categories.php" class="active">Categorías</a></li>
        <li><a href="orders.php">Pedidos</a></li>
        <li><a href="settings.php">Configuración</a></li>
      </ul>
    </div>

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
