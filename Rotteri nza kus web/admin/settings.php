<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Check if user is admin
requireAdmin();

// Get admin ID
$admin_id = getCurrentAdminId($pdo);

// Get admin info
$stmt = $pdo->prepare("SELECT a.*, u.first_name, u.last_name, u.email FROM admins a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = trim($_POST['company_name'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($company_name) || empty($first_name) || empty($last_name) || empty($email)) {
        $error = 'Por favor complete todos los campos obligatorios';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Por favor ingrese un correo electrónico válido';
    } else {
        // Update admin info
        $pdo->beginTransaction();
        try {
            // Update user info
            $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
            $stmt->execute([$first_name, $last_name, $email, $admin['user_id']]);
            
            // Update admin info
            $stmt = $pdo->prepare("UPDATE admins SET company_name = ? WHERE id = ?");
            $stmt->execute([$company_name, $admin_id]);
            
            // Update password if provided
            if (!empty($new_password)) {
                if (empty($current_password)) {
                    $error = 'Por favor ingrese su contraseña actual para cambiarla';
                } elseif (strlen($new_password) < 6) {
                    $error = 'La nueva contraseña debe tener al menos 6 caracteres';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'Las contraseñas nuevas no coinciden';
                } else {
                    // Verify current password
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$admin['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!password_verify($current_password, $user['password'])) {
                        $error = 'La contraseña actual es incorrecta';
                    } else {
                        // Update password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $admin['user_id']]);
                        $success = 'Información actualizada exitosamente';
                    }
                }
            } else {
                $success = 'Información actualizada exitosamente';
            }
            
            if (empty($error)) {
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error al actualizar la información. Por favor intente nuevamente.';
        }
    }
    
    // Refresh admin info
    $stmt = $pdo->prepare("SELECT a.*, u.first_name, u.last_name, u.email FROM admins a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Rotteri Nza Kus</title>
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
        
        .form-group {
            margin-bottom: 20px;
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
        
        .password-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-top: 30px;
        }
        
        .password-section h3 {
            margin-top: 0;
            color: #2c3e50;
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
            <h1>Configuración</h1>
            <p>Bienvenido, <?php echo htmlspecialchars(currentUserName()); ?></p>
        </div>
        
        <div class="admin-nav">
            <ul>
                <li><a href="index.php">Productos</a></li>
                <li><a href="orders.php">Pedidos</a></li>
                <li><a href="settings.php" class="active">Configuración</a></li>
            </ul>
        </div>
        
        <div class="admin-content">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="company_name">Nombre de la Empresa</label>
                    <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($admin['company_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="first_name">Nombre</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($admin['first_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Apellido</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($admin['last_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Correo Electrónico</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
            </form>
            
            <div class="password-section">
                <h3>Cambiar Contraseña</h3>
                <form method="POST">
                    <div class="form-group">
                        <label for="current_password">Contraseña Actual</label>
                        <input type="password" id="current_password" name="current_password">
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Nueva Contraseña</label>
                        <input type="password" id="new_password" name="new_password">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirmar Nueva Contraseña</label>
                        <input type="password" id="confirm_password" name="confirm_password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-key"></i> Cambiar Contraseña
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>