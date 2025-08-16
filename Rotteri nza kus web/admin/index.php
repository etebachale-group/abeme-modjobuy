<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user is admin
requireAdmin();

// Get admin ID
$admin_id = getCurrentAdminId($pdo);

// Get products for this admin
$stmt = $pdo->prepare("SELECT * FROM products WHERE admin_id = ? ORDER BY created_at DESC");
$stmt->execute([$admin_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for form
$stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Rotteri Nza Kus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
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
        
        .admin-nav {
            background: #34495e;
            padding: 10px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .admin-nav ul {
            list-style: none;
            display: flex;
            margin: 0;
            padding: 0;
        }
        
        .admin-nav li {
            margin-right: 20px;
        }
        
        .admin-nav a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .admin-nav a:hover,
        .admin-nav a.active {
            background: #3498db;
        }
        
        .admin-content {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .product-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .product-image {
            height: 200px;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-info {
            padding: 15px;
        }
        
        .product-name {
            font-size: 1.2rem;
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        
        .product-price {
            font-weight: bold;
            color: #27ae60;
            font-size: 1.1rem;
            margin: 5px 0;
        }
        
        .product-weight {
            background: #f1f2f6;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-block;
            margin: 5px 0;
        }
        
        .product-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .btn-edit {
            background: #3498db;
            color: white;
            flex: 1;
            text-align: center;
        }
        
        .btn-edit:hover {
            background: #2980b9;
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
            flex: 1;
            text-align: center;
        }
        
        .btn-delete:hover {
            background: #c0392b;
        }
        
        .btn-add {
            background: #27ae60;
            color: white;
            padding: 12px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .btn-add:hover {
            background: #219653;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: none;
            border-radius: 10px;
            width: 80%;
            max-width: 600px;
            position: relative;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            right: 20px;
            top: 15px;
            cursor: pointer;
        }
        
        .close:hover,
        .close:focus {
            color: black;
        }
        
        .form-group {
            margin-bottom: 15px;
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
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
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
    </style>
</head>
<body>
    <!-- Header -->
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
                                <li><a href="index.php" class="active">Panel Admin</a></li>
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
                <div class="cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count">0</span>
                </div>
                <div class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </div>
    </header>

    <div class="admin-container">
        <div class="admin-header">
            <h1>Panel de Administración</h1>
            <p>Bienvenido, <?php echo htmlspecialchars(currentUserName()); ?></p>
        </div>
        
        <div class="admin-nav">
            <ul>
                <li><a href="index.php" class="active">Productos</a></li>
                <li><a href="orders.php">Pedidos</a></li>
                <li><a href="settings.php">Configuración</a></li>
            </ul>
        </div>
        
        <div class="admin-content">
            <a href="#" class="btn-add" id="addProductBtn">
                <i class="fas fa-plus"></i> Agregar Producto
            </a>
            
            <h2>Mis Productos</h2>
            
            <div class="product-grid">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="product-card">
                            <div class="product-image">
                                <img src="<?php echo $product['image_url']; ?>" alt="<?php echo $product['name']; ?>">
                            </div>
                            <div class="product-info">
                                <h3 class="product-name"><?php echo $product['name']; ?></h3>
                                <p class="product-price">CFA <?php echo number_format($product['price'], 2); ?></p>
                                <span class="product-weight"><?php echo $product['weight']; ?> kg</span>
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
            <form id="addProductForm">
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
                    <input type="number" id="productPrice" name="price" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="productWeight">Peso (kg)</label>
                    <input type="number" id="productWeight" name="weight" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="productImage">URL de la Imagen</label>
                    <input type="text" id="productImage" name="image_url" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-plus"></i> Agregar Producto
                </button>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('addProductModal');
            const btn = document.getElementById('addProductBtn');
            const span = document.getElementsByClassName('close')[0];
            
            btn.onclick = function() {
                modal.style.display = 'block';
            }
            
            span.onclick = function() {
                modal.style.display = 'none';
            }
            
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }
            
            // Form submission
            const form = document.getElementById('addProductForm');
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // In a real implementation, this would send data to the server
                // For now, we'll just close the modal
                alert('Producto agregado exitosamente');
                modal.style.display = 'none';
                form.reset();
            });
        });
    </script>
</body>
</html>