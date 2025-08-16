<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Get products from database
$stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 ORDER BY p.created_at DESC");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $pdo->prepare("SELECT * FROM categories");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rotteri Nza Kus - Compras Online</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <img src="img/logo-without-bg.png" alt="Rotteri Nza Kus Logo">
                    <h1>Rotteri Nza Kus</h1>
                </div>
                <nav class="nav">
                    <ul class="nav-menu">
                        <li><a href="index.php" class="active">Inicio</a></li>
                        <li><a href="#products">Productos</a></li>
                        <li><a href="#contact">Contacto</a></li>
                        <?php if (isAuthenticated()): ?>
                            <?php if (isAdmin()): ?>
                                <li><a href="admin/index.php">Panel Admin</a></li>
                            <?php else: ?>
                                <li><a href="profile.php">Mi Perfil</a></li>
                            <?php endif; ?>
                            <li><a href="logout.php">Cerrar Sesión</a></li>
                        <?php else: ?>
                            <li><a href="login.php">Iniciar Sesión</a></li>
                            <li><a href="register.php">Registrarse</a></li>
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

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h2>Compra productos desde Ghana y Guinea Ecuatorial</h2>
                <p>Envíos confiables y eficientes con seguimiento en tiempo real</p>
                <img src="img/BANNER1.png" alt="Rotteri Nza Kus Banner" class="hero-banner">
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section id="products" class="products">
        <div class="container">
            <h2 class="section-title">Nuestros Productos</h2>
            
            <!-- Filters -->
            <div class="filters">
                <select id="categoryFilter">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo $category['name']; ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="searchFilter" placeholder="Buscar productos...">
            </div>
            
            <!-- Products Grid -->
            <div class="products-grid">
                <?php if (count($products) > 0): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="product-card" data-category="<?php echo $product['category_id']; ?>">
                            <div class="product-image">
                                <img src="<?php echo $product['image_url']; ?>" alt="<?php echo $product['name']; ?>">
                            </div>
                            <div class="product-info">
                                <h3 class="product-name"><?php echo $product['name']; ?></h3>
                                <p class="product-category"><?php echo $product['category_name']; ?></p>
                                <p class="product-description"><?php echo substr($product['description'], 0, 100); ?>...</p>
                                <div class="product-details">
                                    <span class="product-price">CFA <?php echo number_format($product['price'], 2); ?></span>
                                    <span class="product-weight"><?php echo $product['weight']; ?> kg</span>
                                </div>
                                <div class="product-actions">
                                    <button class="btn btn-cart" data-product-id="<?php echo $product['id']; ?>">
                                        <i class="fas fa-shopping-cart"></i> Añadir al carrito
                                    </button>
                                    <button class="btn btn-buy" data-product-id="<?php echo $product['id']; ?>">
                                        <i class="fas fa-bolt"></i> Comprar
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-products">
                        <p>No hay productos disponibles en este momento.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Modal for purchase confirmation -->
    <div id="purchaseModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Confirmar Compra</h2>
            <div id="modalProductDetails"></div>
            <button id="confirmPurchase" class="btn btn-primary">Proceder al Checkout</button>
        </div>
    </div>

    <!-- Contact Section -->
    <section id="contact" class="contact">
        <div class="container">
            <h2 class="section-title">Contacto</h2>
            <div class="contact-info">
                <p>Para más información, contáctanos:</p>
                <p><i class="fas fa-envelope"></i> info@rotterinzakus.com</p>
                <p><i class="fas fa-phone"></i> +233 123 456 789</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; 2025 Rotteri Nza Kus. Todos los derechos reservados.</p>
        </div>
    </footer>

    <script src="js/script.js"></script>
</body>
</html>
