<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Defensive: ensure products & categories tables exist and have needed columns
try {
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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(category_id), INDEX(admin_id),
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Add missing admin_id / created_at columns if legacy table
    try { $pdo->exec("ALTER TABLE products ADD COLUMN admin_id INT NULL"); } catch (Exception $ignore) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"); } catch (Exception $ignore) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN tags VARCHAR(500) NULL"); } catch (Exception $ignore) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN source_url VARCHAR(500) NULL"); } catch (Exception $ignore) {}
    // Ensure is_active and stock exist for consistent behavior across the app
    try { $pdo->exec("ALTER TABLE products ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1"); } catch (Exception $ignore) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN stock INT NULL"); } catch (Exception $ignore) {}
} catch (Exception $e) {
    // Silent; page will show empty lists if fails
}

// Check if user is admin
requireAdmin();

// Get admin ID
$admin_id = getCurrentAdminId($pdo);

// Get products for this admin (fallback if admin_id column missing)
$products = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE admin_id = ? ORDER BY created_at DESC");
    $stmt->execute([$admin_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Retry without admin filter if column absent
    try {
        $stmt = $pdo->prepare("SELECT * FROM products ORDER BY id DESC");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $ignore) {}
}

// Get categories for form (robust against missing/damaged table)
$categories = [];
$categories_notice = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If the table is missing or inconsistent (e.g., InnoDB 1932), try to recreate it gracefully
    $categories_notice = 'Advertencia: la tabla de categorías no estaba disponible. Intentando repararla automáticamente…';
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $ignore) {}

    // Re-try the query; if it still fails, perform a DROP + CREATE as a last resort
    try {
        $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $categories_notice = $categories_notice . ' (Reparada)';
    } catch (Exception $e2) {
        try {
            // Temporarily disable FK checks to allow dropping the referenced table if needed
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            $pdo->exec("DROP TABLE IF EXISTS categories");
            $pdo->exec("CREATE TABLE categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
            // Still show an empty list but without breaking the page
            $categories = [];
            $categories_notice = $categories_notice . ' (Recreada)';
        } catch (Exception $e3) {
            try { $pdo->exec("SET FOREIGN_KEY_CHECKS=1"); } catch (Exception $ignore4) {}
            $categories_notice = 'Error crítico: no se pudo recrear la tabla de categorías. ' . htmlspecialchars($e3->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Rotteri Nza Kus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/modern.css">
    <link rel="stylesheet" href="../css/toast.css">
    <style>
        /* Admin layout */
        .admin-container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .admin-header { background: #2c3e50; color: #fff; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .admin-header h1 { margin: 0; font-size: 2rem; }

        /* Grid */
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; padding: 20px 0; }

        /* Product card (dark, compact, modern) */
        .product-card {
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,.25);
            background: linear-gradient(180deg, rgba(28,37,65,.95), rgba(28,37,65,.85));
            color: #e5e7eb;
            display: flex; flex-direction: column;
            transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
        }
        .product-card:hover { transform: translateY(-6px); box-shadow: 0 18px 36px rgba(0,0,0,.35); border-color: rgba(255,255,255,.15); }

        .product-image { aspect-ratio: 3 / 2; overflow: hidden; }
        .product-image img { width: 100%; height: 100%; object-fit: cover; display: block; }

        .product-info { padding: 12px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
        .product-name { font-size: 1rem; margin: 0; color: #e5e7eb; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .product-price { font-weight: 700; color: #27ae60; font-size: 1.1rem; margin: 5px 0; }
        .product-weight { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); color: #e5e7eb; padding: 3px 8px; border-radius: 20px; font-size: .8rem; display: inline-block; margin: 5px 0; }
        .product-actions { margin-top: auto; display: flex; gap: 10px; }

        /* Buttons */
        .btn { padding: 8px 15px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; transition: background .3s; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
        .btn-edit { background: #3498db; color: #fff; flex: 1; text-align: center; }
        .btn-edit:hover { background: #2980b9; }
        .btn-delete { background: #e74c3c; color: #fff; flex: 1; text-align: center; }
        .btn-delete:hover { background: #c0392b; }
        .btn-add { background: #27ae60; color: #fff; padding: 12px 20px; border-radius: 5px; text-decoration: none; display: inline-block; margin-bottom: 20px; font-weight: 600; }
        .btn-add:hover { background: #219653; }

        /* Modal (dark) */
        .modal { display: none; position: fixed; z-index: 1000; inset: 0; width: 100%; height: 100%; background: rgba(0,0,0,.5); }
        .modal-content { background: linear-gradient(180deg, rgba(28,37,65,.98), rgba(28,37,65,.92)); color:#e5e7eb; margin: 5% auto; padding: 20px; border: 1px solid rgba(255,255,255,.08); border-radius: 12px; width: 80%; max-width: 600px; position: relative; max-height: 85vh; overflow-y: auto; -webkit-overflow-scrolling: touch; box-shadow: 0 20px 50px rgba(0,0,0,.45); }
        .close { color: #cbd5e1; float: right; font-size: 28px; font-weight: bold; position: absolute; right: 20px; top: 15px; cursor: pointer; }
        .close:hover, .close:focus { color: #fff; }

        /* Form (dark) */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 10px; border: 1px solid rgba(255,255,255,.14); border-radius: 8px; font-size: 1rem; background: rgba(255,255,255,.06); color: #e5e7eb; }
        .form-group input::placeholder, .form-group textarea::placeholder { color: #9aa4b2; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { outline: none; border-color: #93c5fd; box-shadow: 0 0 0 3px rgba(59,130,246,.25); }
        .form-group textarea { min-height: 100px; resize: vertical; }
        .btn-primary { background: #9b59b6; color: #fff; padding: 12px 20px; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; font-size: 1rem; }
        .btn-primary:hover { background: #8e44ad; }
        .btn-block { display: block; width: 100%; }

        /* Tags + empty state */
        .tags { margin-top: 6px; display: flex; flex-wrap: wrap; gap: 6px; }
        .tag-chip { background: rgba(255,255,255,.08); color:#cbd5e1; padding:4px 8px; border-radius:12px; font-size:.8rem; border:1px solid rgba(255,255,255,.12); }
        .no-products { text-align:center; padding: 28px; color:#e5e7eb; background: rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:10px; grid-column: 1/-1; }

        @media (max-width: 640px) {
            .modal-content { width: 92%; margin: 4vh auto; max-height: 90vh; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/layout_header.php'; ?>

    <div class="admin-container">
        <div class="admin-header">
            <h1>Panel de Administración</h1>
            <p>Bienvenido, <?php echo htmlspecialchars(trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?: ($_SESSION['user_email'] ?? 'Admin')); ?></p>
        </div>
        
        <?php include __DIR__ . '/../includes/admin_navbar.php'; ?>
        
        <div class="admin-content">
            <?php if (!empty($categories_notice)): ?>
            <div id="catRepairBox" style="margin: 12px 0; padding: 10px 14px; border-radius: 10px; border:1px solid rgba(255,255,255,.14); background: linear-gradient(180deg, rgba(28,37,65,.98), rgba(28,37,65,.92)); color:#e5e7eb;">
                <i class="fas fa-info-circle" aria-hidden="true"></i>
                <strong>Mantenimiento:</strong> <?php echo htmlspecialchars($categories_notice); ?>
                <div style="margin-top:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <button id="retryRepair" class="btn btn-primary"><i class="fas fa-wrench"></i> Reintentar reparación</button>
                    <span id="repairStatus" style="color:#cbd5e1;"></span>
                </div>
            </div>
            <?php endif; ?>
            <a href="#" class="btn-add" id="addProductBtn">
                <i class="fas fa-plus"></i> Agregar Producto
            </a>
            
            <h2>Mis Productos</h2>
            
            <div class="product-grid">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                        <?php
                            $imgSrc = $product['image_url'] ?? '';
                            if ($imgSrc && strpos($imgSrc, 'http') !== 0 && strpos($imgSrc, '../') !== 0 && strpos($imgSrc, '/') !== 0) {
                                $imgSrc = '../' . ltrim($imgSrc, '/');
                            }
                            $tagsStr = trim((string)($product['tags'] ?? ''));
                            $tagsArr = array_filter(array_map('trim', explode(',', $tagsStr)));
                        ?>
                    <div class="product-card"
                        data-product-id="<?php echo (int)$product['id']; ?>"
                        data-name="<?php echo htmlspecialchars((string)$product['name'], ENT_QUOTES); ?>"
                        data-description="<?php echo htmlspecialchars((string)$product['description'], ENT_QUOTES); ?>"
                        data-category-id="<?php echo htmlspecialchars((string)$product['category_id']); ?>"
                        data-price="<?php echo htmlspecialchars((string)$product['price']); ?>"
                        data-weight="<?php echo htmlspecialchars((string)$product['weight']); ?>"
                        data-image-url="<?php echo htmlspecialchars((string)$product['image_url'], ENT_QUOTES); ?>"
                        data-tags="<?php echo htmlspecialchars($tagsStr); ?>"
                        data-source-url="<?php echo htmlspecialchars((string)($product['source_url'] ?? ''), ENT_QUOTES); ?>">
                            <div class="product-image">
                                <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            </div>
                            <div class="product-info">
                                <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="product-price">CFA <?php echo number_format((float)$product['price'], 2); ?></p>
                                <span class="product-weight"><?php echo htmlspecialchars((string)$product['weight']); ?> kg</span>
                                <?php if (!empty($tagsArr)): ?>
                                <div class="tags">
                                    <?php foreach ($tagsArr as $tg): ?>
                                        <span class="tag-chip">#<?php echo htmlspecialchars($tg); ?></span>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                <?php $src = trim((string)($product['source_url'] ?? '')); if ($src !== ''): ?>
                                <div class="product-source" style="margin-top:8px">
                                    <a href="<?php echo htmlspecialchars($src); ?>" target="_blank" rel="noopener noreferrer">
                                        <i class="fas fa-link"></i> Ver proveedor
                                    </a>
                                </div>
                                <?php endif; ?>
                                <div class="product-actions">
                                    <a href="#" class="btn btn-edit" data-product-id="<?php echo $product['id']; ?>">
                                        <i class="fas fa-edit"></i> Editar
                                    </a>
                                    <a href="#" class="btn btn-delete" data-product-id="<?php echo $product['id']; ?>">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-products">
                        <p>No tienes productos registrados.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Agregar Nuevo Producto</h2>
            <form id="addProductForm" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <div class="form-group">
                    <label for="productName">Nombre del Producto</label>
                    <input type="text" id="productName" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="productDescription">Descripción</label>
                    <textarea id="productDescription" name="description" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="productCategory">Categoría</label>
                    <select id="productCategory" name="category_id" required>
                        <option value="">Seleccionar Categoría</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="productPrice">Precio (CFA)</label>
                    <input type="number" id="productPrice" name="price" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="productWeight">Peso (kg)</label>
                    <input type="number" id="productWeight" name="weight" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label for="productImage">URL de la Imagen</label>
                    <input type="text" id="productImage" name="image_url" placeholder="Opcional si subes archivo">
                </div>

                <div class="form-group">
                    <label for="productSourceUrl">Enlace de proveedor (opcional)</label>
                    <input type="url" id="productSourceUrl" name="source_url" placeholder="https://...">
                </div>

                <div class="form-group">
                    <label for="productTags">Etiquetas (separadas por comas)</label>
                    <input type="text" id="productTags" name="tags" placeholder="ej: fresco, orgánico, bebida">
                </div>
                
                <div class="form-group">
                    <label for="productImageFile">Archivo de Imagen</label>
                    <input type="file" id="productImageFile" name="image_file" accept="image/*">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-plus"></i> Agregar Producto
                </button>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editProductModal" class="modal">
        <div class="modal-content">
            <span class="close" data-close="#editProductModal">&times;</span>
            <h2>Editar Producto</h2>
            <form id="editProductForm" enctype="multipart/form-data">
                <input type="hidden" name="product_id" id="edit_id" />
                <div class="form-group">
                    <label for="edit_name">Nombre del Producto</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="edit_description">Descripción</label>
                    <textarea id="edit_description" name="description" required></textarea>
                </div>
                <div class="form-group">
                    <label for="edit_category_id">Categoría</label>
                    <select id="edit_category_id" name="category_id" required>
                        <option value="">Seleccionar Categoría</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_price">Precio (CFA)</label>
                    <input type="number" id="edit_price" name="price" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="edit_weight">Peso (kg)</label>
                    <input type="number" id="edit_weight" name="weight" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="edit_image_url">URL de la Imagen</label>
                    <input type="text" id="edit_image_url" name="image_url" placeholder="Opcional si subes archivo">
                </div>
                <div class="form-group">
                    <label for="edit_source_url">Enlace de proveedor (opcional)</label>
                    <input type="url" id="edit_source_url" name="source_url" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label for="edit_image_file">Archivo de Imagen (opcional)</label>
                    <input type="file" id="edit_image_file" name="image_file" accept="image/*">
                </div>
                <div class="form-group">
                    <label for="edit_tags">Etiquetas (separadas por comas)</label>
                    <input type="text" id="edit_tags" name="tags" placeholder="ej: fresco, orgánico, bebida">
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('addProductModal');
            const btn = document.getElementById('addProductBtn');
            const closeButtons = document.querySelectorAll('.modal .close');
            btn.onclick = function() { modal.style.display = 'block'; }
            closeButtons.forEach(c => c.addEventListener('click', (e)=>{
                const targetSel = c.getAttribute('data-close');
                const m = targetSel ? document.querySelector(targetSel) : c.closest('.modal');
                if (m) m.style.display = 'none';
            }));
            window.addEventListener('click', (e) => {
                document.querySelectorAll('.modal').forEach(m => { if (e.target === m) m.style.display = 'none'; });
            });
            
            // Form submission via AJAX (multipart)
            const form = document.getElementById('addProductForm');
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const fd = new FormData(form);
                // Ensure CSRF token present
                if (!fd.get('csrf_token')) { fd.append('csrf_token', '<?php echo htmlspecialchars(csrf_token()); ?>'); }
                try {
                    const res = await fetch('add_product.php', {
                        method: 'POST',
                        body: fd
                    });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Error desconocido');
                    // Close and reset
                    modal.style.display = 'none';
                    form.reset();
                    // Optionally reload to reflect new product
                    location.reload();
                } catch (err) {
                    toast.error('No se pudo agregar el producto: ' + err.message);
                }
            });

            // Delete product via AJAX
            document.querySelectorAll('.btn-delete').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const id = btn.getAttribute('data-product-id');
                    if (!confirm('¿Eliminar producto?')) return;
                    try {
                        const fd = new FormData();
                        fd.append('csrf_token', '<?php echo htmlspecialchars(csrf_token()); ?>');
                        fd.append('product_id', id);
                        const res = await fetch('delete_product.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (!data.success) throw new Error(data.message || 'Error');
                        location.reload();
                    } catch (err) {
                        toast.error('No se pudo eliminar: ' + err.message);
                    }
                });
            });

            // Full edit via modal
            const editModal = document.getElementById('editProductModal');
            const editForm = document.getElementById('editProductForm');
            document.querySelectorAll('.btn-edit').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const card = btn.closest('.product-card');
                    if (!card) return;
                    document.getElementById('edit_id').value = card.getAttribute('data-product-id') || '';
                    document.getElementById('edit_name').value = card.getAttribute('data-name') || '';
                    document.getElementById('edit_description').value = card.getAttribute('data-description') || '';
                    document.getElementById('edit_category_id').value = card.getAttribute('data-category-id') || '';
                    document.getElementById('edit_price').value = card.getAttribute('data-price') || '';
                    document.getElementById('edit_weight').value = card.getAttribute('data-weight') || '';
                    document.getElementById('edit_image_url').value = card.getAttribute('data-image-url') || '';
                    document.getElementById('edit_source_url').value = card.getAttribute('data-source-url') || '';
                    document.getElementById('edit_tags').value = card.getAttribute('data-tags') || '';
                    editModal.style.display = 'block';
                });
            });
            editForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const fd = new FormData(editForm);
                if (!fd.get('csrf_token')) { fd.append('csrf_token', '<?php echo htmlspecialchars(csrf_token()); ?>'); }
                try {
                    const res = await fetch('update_product.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) throw new Error(data.message || 'Error');
                    // Update card DOM
                    const id = fd.get('product_id');
                    const card = document.querySelector(`.product-card[data-product-id="${id}"]`);
                    if (card) {
                        const name = fd.get('name')?.toString() || '';
                        const description = fd.get('description')?.toString() || '';
                        const categoryId = fd.get('category_id')?.toString() || '';
                        const price = fd.get('price')?.toString() || '';
                        const weight = fd.get('weight')?.toString() || '';
                        const tags = fd.get('tags')?.toString() || '';
                        const sourceUrl = fd.get('source_url')?.toString() || '';
                        const img = data.image_url || fd.get('image_url')?.toString() || card.getAttribute('data-image-url') || '';
                        // Update dataset
                        card.setAttribute('data-name', name);
                        card.setAttribute('data-description', description);
                        card.setAttribute('data-category-id', categoryId);
                        card.setAttribute('data-price', price);
                        card.setAttribute('data-weight', weight);
                        card.setAttribute('data-tags', tags);
                        card.setAttribute('data-image-url', img);
                        card.setAttribute('data-source-url', sourceUrl);
                        // Update visible fields
                        const info = card.querySelector('.product-info');
                        info.querySelector('.product-name').textContent = name;
                        info.querySelector('.product-price').textContent = 'CFA ' + Number(price||0).toFixed(2);
                        info.querySelector('.product-weight').textContent = (weight||'') + ' kg';
                        const imgEl = card.querySelector('img');
                        if (imgEl) imgEl.src = (img.startsWith('http') || img.startsWith('../') || img.startsWith('/')) ? img : ('../' + img.replace(/^\/+/, ''));
                        // Tags
                        const tagsArr = tags.split(',').map(s=>s.trim()).filter(Boolean);
                        let tagsBox = card.querySelector('.tags');
                        if (!tagsArr.length) { if (tagsBox) tagsBox.remove(); }
                        else {
                            if (!tagsBox) {
                                tagsBox = document.createElement('div');
                                tagsBox.className = 'tags';
                                info.insertBefore(tagsBox, info.querySelector('.product-actions'));
                            }
                            // Clear and append safely
                            while (tagsBox.firstChild) tagsBox.removeChild(tagsBox.firstChild);
                            tagsArr.forEach(t => {
                                const chip = document.createElement('span');
                                chip.className = 'tag-chip';
                                chip.textContent = '#' + t;
                                tagsBox.appendChild(chip);
                            });
                        }
                    }
                    editModal.style.display = 'none';
                } catch (err) {
                    toast.error('No se pudo actualizar: ' + err.message);
                }
            });

            // Repair categories handler
            const retryBtn = document.getElementById('retryRepair');
            const statusEl = document.getElementById('repairStatus');
            if (retryBtn) {
                retryBtn.addEventListener('click', async ()=>{
                    statusEl.textContent = 'Reparando…';
                    try {
                        const fd = new FormData();
                        try { fd.append('csrf_token', '<?php echo htmlspecialchars(csrf_token()); ?>'); } catch(e){}
                        const res = await fetch('repair_categories.php', { method: 'POST', body: fd });
                        const data = await res.json();
                        if (data.success) {
                            statusEl.textContent = 'Tabla recreada. Recargando…';
                            setTimeout(()=> location.reload(), 900);
                        } else {
                            statusEl.textContent = 'No se pudo reparar automáticamente.';
                            if (data.manual) {
                                const box = document.getElementById('catRepairBox');
                                const steps = document.createElement('div');
                                steps.style.marginTop = '8px';
                                const title = document.createElement('strong');
                                title.textContent = data.manual.title + ':';
                                steps.appendChild(title);
                                const ul = document.createElement('ol');
                                data.manual.steps.forEach(s=>{ const li=document.createElement('li'); li.textContent = s; ul.appendChild(li); });
                                steps.appendChild(ul);
                                const note = document.createElement('div');
                                note.style.opacity = '0.85';
                                note.textContent = data.manual.note || '';
                                steps.appendChild(note);
                                box.appendChild(steps);
                            }
                        }
                    } catch (err) {
                        statusEl.textContent = 'Error de red: ' + (err?.message || err);
                    }
                });
            }
        });
    </script>
    <script src="../js/toast.js"></script>
</body>
</html>