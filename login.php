<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Si el usuario ya está autenticado, redirigir a admin
if (isAuthenticated()) {
    header('Location: admin.php');
    exit;
}

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $user = authenticateUser($pdo, $email, $password);
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        header('Location: admin.php');
        exit;
    } else {
        $error = "Credenciales incorrectas. Por favor, inténtalo de nuevo.";
        
    }
}
?>

<?php include 'includes/header.php'; ?>

    <section class="login-container">
        <h2 class="section-title">Iniciar Sesión</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form id="login-form" method="POST">
            <div class="form-group">
                <label for="login-email">Correo electrónico *</label>
                <input type="email" id="login-email" name="email" required>
            </div>
            <div class="form-group">
                <label for="login-password">Contraseña *</label>
                <input type="password" id="login-password" name="password" required>
            </div>
            <button type="submit" class="btn">Acceder</button>
        </form>
    </section>

<?php include 'includes/footer.php'; ?>
