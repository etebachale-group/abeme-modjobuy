<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user is admin
requireAdmin();

// Get admin ID
$admin_id = getCurrentAdminId($pdo);

// Get orders for this admin's products
$stmt = $pdo->prepare("SELECT o.*, oi.product_name, oi.quantity, oi.unit_price, u.first_name, u.last_name FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN users u ON o.user_id = u.id WHERE oi.product_id IN (SELECT id FROM products WHERE admin_id = ?) ORDER BY o.created_at DESC");
$stmt->execute([$admin_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Pedidos - Rotteri Nza Kus</title>
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
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .orders-table th {
            background: #f2f2f2;
            font-weight: bold;
        }
        
        .orders-table tr:hover {
            background: #f9f9f9;
        }
        
        .order-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-processing {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-shipped {
            background: #bee5eb;
            color: #0c5460;
        }
        
        .status-delivered {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .btn-accept {
            background: #27ae60;
            color: white;
        }
        
        .btn-accept:hover {
            background: #219653;
        }
        
        .btn-reject {
            background: #e74c3c;
            color: white;
        }
        
        .btn-reject:hover {
            background: #c0392b;
        }
        
        .btn-view {
            background: #3498db;
            color: white;
        }
        
        .btn-view:hover {
            background: #2980b9;
        }
        
        .no-orders {
            text-align: center;
            padding: 40px;
            color: #666;
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
                                <li><a href="index.php">Panel Admin</a></li>
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
            <h1>Gestión de Pedidos</h1>
            <p>Bienvenido, <?php echo htmlspecialchars(currentUserName()); ?></p>
        </div>
        
        <div class="admin-nav">
            <ul>
                <li><a href="index.php">Productos</a></li>
                <li><a href="orders.php" class="active">Pedidos</a></li>
                <li><a href="settings.php">Configuración</a></li>
            </ul>
        </div>
        
        <div class="admin-content">
            <h2>Pedidos Recibidos</h2>
            
            <?php if (count($orders) > 0): ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Orden #</th>
                            <th>Cliente</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Total</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo $order['order_number']; ?></td>
                                <td><?php echo $order['first_name'] . ' ' . $order['last_name']; ?></td>
                                <td><?php echo $order['product_name']; ?></td>
                                <td><?php echo $order['quantity']; ?></td>
                                <td>CFA <?php echo number_format($order['unit_price'], 2); ?></td>
                                <td>CFA <?php echo number_format($order['unit_price'] * $order['quantity'], 2); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <span class="order-status status-<?php echo $order['status']; ?>">
                                        <?php 
                                        switch ($order['status']) {
                                            case 'pending': echo 'Pendiente'; break;
                                            case 'confirmed': echo 'Confirmado'; break;
                                            case 'processing': echo 'Procesando'; break;
                                            case 'shipped': echo 'Enviado'; break;
                                            case 'delivered': echo 'Entregado'; break;
                                            case 'cancelled': echo 'Cancelado'; break;
                                            default: echo ucfirst($order['status']);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($order['status'] == 'pending'): ?>
                                        <button class="btn btn-accept" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'confirmed')">
                                            <i class="fas fa-check"></i> Aceptar
                                        </button>
                                        <button class="btn btn-reject" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'cancelled')">
                                            <i class="fas fa-times"></i> Rechazar
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-view" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-eye"></i> Ver
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-orders">
                    <p>No hay pedidos pendientes.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function updateOrderStatus(orderId, status) {
            if (confirm('¿Está seguro de que desea ' + (status === 'confirmed' ? 'aceptar' : 'rechazar') + ' este pedido?')) {
                // In a real implementation, this would send data to the server
                alert('Pedido ' + (status === 'confirmed' ? 'aceptado' : 'rechazado') + ' exitosamente');
                location.reload();
            }
        }
        
        function viewOrder(orderId) {
            // In a real implementation, this would show order details
            alert('Ver detalles del pedido #' + orderId);
        }
    </script>
</body>
</html>