<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$orderNumber = $_GET['order'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Confirmación de pedido • Rotteri Nza Kus</title>
	<meta name="theme-color" content="#0b132b">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
	<link rel="stylesheet" href="css/style.css">
	<link rel="stylesheet" href="css/modern.css">
	<link rel="stylesheet" href="css/order-confirmation.css">
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
						<?php if (isAuthenticated()): ?>
							<li><a href="profile.php">Mi Perfil</a></li>
						<?php else: ?>
							<li><a href="login.php">Iniciar Sesión</a></li>
						<?php endif; ?>
					</ul>
				</nav>
				<?php if (isAuthenticated()) { include __DIR__ . '/includes/notifications_ui.php'; } ?>
			</div>
		</div>
	</header>

	<!-- Confirmation Section -->
	<section class="confirmation">
		<div class="container">
			<div class="confirm-card">
				<div class="icon success"><i class="fas fa-check-circle"></i></div>
				<h2 class="title">¡Gracias por tu compra!</h2>
				<?php if ($orderNumber): ?>
					<p class="subtitle">Tu número de pedido es <strong>#<?php echo htmlspecialchars($orderNumber); ?></strong>.</p>
				<?php else: ?>
					<p class="subtitle">Tu pedido ha sido generado correctamente.</p>
				<?php endif; ?>
				<p class="hint">Te enviaremos actualizaciones del estado de tu pedido a tu correo.</p>

				<div class="actions">
					<a class="btn btn-primary" href="profile.php"><i class="fas fa-box"></i> Ver mis pedidos</a>
					<a class="btn btn-buy" href="index.php#products"><i class="fas fa-store"></i> Seguir comprando</a>
				</div>
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
	<?php include __DIR__ . '/includes/cart_ui.php'; ?>
</body>
</html>
