<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Check if user is authenticated
requireAuth();

// Self-healing: ensure required tables exist (minimal columns for this page)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        first_name VARCHAR(100) NULL,
        last_name VARCHAR(100) NULL,
        phone VARCHAR(50) NULL,
        address VARCHAR(255) NULL,
        city VARCHAR(100) NULL,
        country VARCHAR(100) NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        order_number VARCHAR(50) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Ignore DDL errors (will show fallback messages later)
}

$user = null;
$orders = [];
$userError = null;
$ordersError = null;

// Get user info safely
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([currentUserId()]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $userError = 'No se pudo cargar el perfil (tabla o datos faltantes).';
}

// Get user orders safely
try {
    $stmt = $pdo->prepare("SELECT o.*, oi.product_name, oi.quantity, oi.unit_price FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE o.user_id = ? ORDER BY o.created_at DESC");
    $stmt->execute([currentUserId()]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $ordersError = 'No se pudieron cargar los pedidos.';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - Rotteri Nza Kus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .profile-header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .profile-header h1 {
            margin: 0;
            font-size: 2rem;
        }
        
        .profile-content {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .profile-content {
                grid-template-columns: 1fr;
            }
        }
        
        .profile-info {
            background: linear-gradient(180deg, rgba(28,37,65,.95), rgba(28,37,65,.85));
            color: #e5e7eb;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,.08);
        }
        
        .profile-info h2 {
            margin-top: 0;
            color: #e5e7eb;
            border-bottom: 2px solid rgba(255,255,255,.12);
            padding-bottom: 10px;
        }
        
        .profile-details {
            margin-bottom: 20px;
        }
        
        .profile-detail {
            margin-bottom: 15px;
        }
        
        .profile-detail label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
            color: #e5e7eb;
        }
        
        .profile-orders {
            background: linear-gradient(180deg, rgba(28,37,65,.95), rgba(28,37,65,.85));
            color: #e5e7eb;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            border: 1px solid rgba(255,255,255,.08);
        }
        
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            color: #e5e7eb;
        }
        
        .orders-table th,
        .orders-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,.12);
        }
        
        .orders-table th {
            background: rgba(255,255,255,.06);
            font-weight: bold;
            color: #e5e7eb;
        }
        
        .orders-table tr:hover {
            background: rgba(255,255,255,.04);
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
        
        .no-orders {
            text-align: center;
            padding: 32px;
            color: #e5e7eb;
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/layout_header.php'; ?>

    <div class="profile-container">
        <div class="profile-header">
            <h1>Mi Perfil</h1>
            <?php if ($user): ?>
                <p>Bienvenido, <?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: $user['email']); ?></p>
            <?php elseif ($userError): ?>
                <p style="color:#ffc107;">Perfil no disponible: <?php echo htmlspecialchars($userError); ?></p>
            <?php else: ?>
                <p style="color:#ffc107;">Tu cuenta existe pero no se encontró el registro de perfil.</p>
            <?php endif; ?>
        </div>
        
        <div class="profile-content">
            <div class="profile-info">
                <h2>Información Personal</h2>
                <?php if ($user): ?>
                    <div class="profile-details">
                        <div class="profile-detail">
                            <label>Nombre Completo</label>
                            <p><?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'No especificado'); ?></p>
                        </div>
                        <div class="profile-detail">
                            <label>Correo Electrónico</label>
                            <p><?php echo htmlspecialchars($user['email'] ?? 'No disponible'); ?></p>
                        </div>
                        <div class="profile-detail">
                            <label>Teléfono</label>
                            <p><?php echo htmlspecialchars($user['phone'] ?? 'No especificado'); ?></p>
                        </div>
                        <div class="profile-detail">
                            <label>Dirección</label>
                            <p><?php echo htmlspecialchars($user['address'] ?? 'No especificada'); ?></p>
                        </div>
                        <div class="profile-detail">
                            <label>Ciudad</label>
                            <p><?php echo htmlspecialchars($user['city'] ?? 'No especificada'); ?></p>
                        </div>
                        <div class="profile-detail">
                            <label>País</label>
                            <p><?php echo htmlspecialchars($user['country'] ?? 'No especificado'); ?></p>
                        </div>
                    </div>
                    <a href="#" class="btn btn-primary">Editar Perfil</a>
                <?php else: ?>
                    <div style="padding:10px;color:#555;">
                        <p>No se pudo cargar tu información personal todavía.</p>
                        <form method="post" action="rebuild_profile_user.php" style="margin-top:10px;">
                            <input type="hidden" name="action" value="create_missing_user">
                            <button class="btn btn-primary" style="background:#3498db;color:#fff;border:none;padding:8px 14px;border-radius:6px;cursor:pointer;">Crear Registro Básico</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-orders">
                <h2>Mis Pedidos</h2>
                <?php if (count($orders) > 0): ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Orden #</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio Unitario</th>
                                <th>Total</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo $order['order_number']; ?></td>
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
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-orders">
                        <p>No tienes pedidos registrados.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>