<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Check if user is authenticated
requireAuth();

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([currentUserId()]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// For now, we'll create a simple checkout page
// In a real implementation, this would fetch cart items from the database
$cart_items = [];
$total_amount = 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Rotteri Nza Kus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .checkout-header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .checkout-header h1 {
            margin: 0;
            font-size: 2rem;
        }
        
        .checkout-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .checkout-content {
                grid-template-columns: 1fr;
            }
        }
        
        .order-summary {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .shipping-info {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1rem;
            transition: background 0.3s;
        }
        
        .btn-primary {
            background: #9b59b6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #8e44ad;
        }
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .cart-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .item-total {
            font-weight: bold;
            margin-top: 20px;
            text-align: right;
        }
    </style>
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
                        <li><a href="index.php">Inicio</a></li>
                        <li><a href="index.php#products">Productos</a></li>
                        <li><a href="index.php#contact">Contacto</a></li>
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

    <div class="checkout-container">
        <div class="checkout-header">
            <h1>Checkout</h1>
            <p>Finaliza tu compra</p>
        </div>
        
        <div class="checkout-content">
            <div class="order-summary">
                <h2>Resumen del Pedido</h2>
                <?php if (count($cart_items) > 0): ?>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div>
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <p>Cantidad: <?php echo $item['quantity']; ?></p>
                            </div>
                            <div>
                                <p>CFA <?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="item-total">
                        <p>Total: CFA <?php echo number_format($total_amount, 2); ?></p>
                    </div>
                <?php else: ?>
                    <p>No hay productos en tu carrito.</p>
                <?php endif; ?>
            </div>
            
            <div class="shipping-info">
                <h2>Información de Envío</h2>
                <form method="POST" id="checkoutForm">
                    <div class="form-group">
                        <label for="fullName">Nombre Completo</label>
                        <input type="text" id="fullName" name="full_name" value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Correo Electrónico</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Teléfono</label>
                        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Dirección</label>
                        <textarea id="address" name="address" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="city">Ciudad</label>
                        <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="country">País</label>
                        <select id="country" name="country" required>
                            <option value="">Seleccionar País</option>
                            <option value="Ghana" <?php echo (isset($user['country']) && $user['country'] == 'Ghana') ? 'selected' : ''; ?>>Ghana</option>
                            <option value="Guinea Ecuatorial" <?php echo (isset($user['country']) && $user['country'] == 'Guinea Ecuatorial') ? 'selected' : ''; ?>>Guinea Ecuatorial</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="paymentMethod">Método de Pago</label>
                        <select id="paymentMethod" name="payment_method" required>
                            <option value="">Seleccionar Método de Pago</option>
                            <option value="transfer">Transferencia Bancaria</option>
                            <option value="cash">Pago en Efectivo</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-shopping-cart"></i> Finalizar Compra
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="js/script.js"></script>
    <script>
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Pedido procesado exitosamente');
            // In a real implementation, this would send data to the server
        });
    </script>
</body>
</html>
