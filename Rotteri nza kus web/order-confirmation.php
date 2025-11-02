<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$orderNumber = trim($_GET['order'] ?? '');
$order = null; $items = [];
if ($orderNumber !== '') {
    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = ? AND user_id = ?');
        $stmt->execute([$orderNumber, currentUserId()]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($order) {
            $it = $pdo->prepare('SELECT product_name, quantity, unit_price FROM order_items WHERE order_id = ?');
            $it->execute([$order['id']]);
            $items = $it->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) { /* ignore */ }
}
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
	<?php include __DIR__ . '/includes/layout_header.php'; ?>

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

				<?php if ($order): ?>
				<div class="order-box" style="background:var(--grad-surface,linear-gradient(180deg,rgba(28,37,65,0.92),rgba(22,32,59,0.92)));padding:16px;border-radius:14px;border:1px solid var(--border,rgba(148,163,184,0.22));box-shadow:0 4px 20px rgba(0,0,0,.30);margin-top:16px;">
					<h3 style="margin:0 0 12px;">Resumen del pedido</h3>
					<?php if (!empty($order['full_name'])): ?>
						<p style="margin:0 0 6px;">A nombre de: <strong><?php echo htmlspecialchars($order['full_name']); ?></strong></p>
					<?php endif; ?>
					<?php if (!empty($order['address'])): ?>
						<p style="margin:0 0 6px;">Envío: <?php echo htmlspecialchars($order['address']); ?><?php echo $order['city']?', '.htmlspecialchars($order['city']):''; ?><?php echo $order['country']?', '.htmlspecialchars($order['country']):''; ?></p>
					<?php endif; ?>
					<?php if (!empty($order['payment_method'])): ?>
						<p style="margin:0 0 6px;">Pago: <strong><?php echo htmlspecialchars($order['payment_method']); ?></strong></p>
					<?php endif; ?>
					<div style="margin-top:10px;border-top:1px solid #eee;padding-top:10px;">
						<?php foreach ($items as $it): ?>
							<div style="display:flex;justify-content:space-between;padding:4px 0;">
								<div><?php echo htmlspecialchars($it['product_name']); ?> × <?php echo (int)$it['quantity']; ?></div>
								<div>CFA <?php echo number_format((float)$it['unit_price'] * (int)$it['quantity'], 2); ?></div>
							</div>
						<?php endforeach; ?>
						<div style="display:flex;justify-content:space-between;margin-top:8px;">
							<div>Subtotal</div>
							<div>CFA <?php echo number_format((float)$order['subtotal'], 2); ?></div>
						</div>
						<div style="display:flex;justify-content:space-between;">
							<div>Envío</div>
							<div>CFA <?php echo number_format((float)$order['shipping'], 2); ?></div>
						</div>
						<div style="display:flex;justify-content:space-between;">
							<div>Impuestos</div>
							<div>CFA <?php echo number_format((float)$order['taxes'], 2); ?></div>
						</div>
						<div style="display:flex;justify-content:space-between;font-weight:700;border-top:1px solid var(--border,rgba(148,163,184,0.22));padding-top:8px;">
							<div>Total</div>
							<div>CFA <?php echo number_format((float)$order['total'], 2); ?></div>
						</div>
					</div>
				</div>

				<div class="payment-instructions" style="margin-top:16px;">
					<?php if ($order['payment_method'] === 'transfer'): ?>
						<div class="notice" style="background:linear-gradient(135deg,rgba(255,209,102,0.18),rgba(91,192,190,0.18));border:1px solid rgba(255,209,102,0.45);color:var(--text,#e5e7eb);padding:14px;border-radius:10px;">
							<h4 style="margin:0 0 6px;">Instrucciones de transferencia</h4>
							<p style="margin:0;">Realiza una transferencia a la cuenta bancaria indicada en el correo de confirmación. Incluye el número de pedido <strong>#<?php echo htmlspecialchars($orderNumber); ?></strong> como referencia.</p>
						</div>
					<?php elseif ($order['payment_method'] === 'cash'): ?>
						<div class="notice" style="background:linear-gradient(135deg,rgba(39,174,96,0.18),rgba(91,192,190,0.15));border:1px solid rgba(39,174,96,0.45);color:var(--text,#e5e7eb);padding:14px;border-radius:10px;">
							<h4 style="margin:0 0 6px;">Pago en efectivo</h4>
							<p style="margin:0;">Podrás pagar en efectivo al momento de la entrega/recogida. Ten a mano el monto total y tu número de pedido.</p>
						</div>
					<?php endif; ?>
				</div>
				<?php endif; ?>

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
