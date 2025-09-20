<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!isAuthenticated() || !isAdmin()) {
	echo json_encode(['success' => false, 'message' => 'No autorizado']);
	exit;
}

// CSRF (if available)
$csrf = $_POST['csrf_token'] ?? '';
if (function_exists('csrf_validate') && !csrf_validate($csrf)) {
	echo json_encode(['success' => false, 'message' => 'CSRF inválido']);
	exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
$status = $_POST['status'] ?? '';
$valid = ['pending','confirmed','processing','shipped','delivered','cancelled'];
if ($orderId <= 0 || !in_array($status, $valid, true)) {
	echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
	exit;
}

try {
	$pdo->beginTransaction();
	// Update order
	$u = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
	$u->execute([$status, $orderId]);

	// Get user and order number
	$s = $pdo->prepare('SELECT user_id, order_number FROM orders WHERE id = ?');
	$s->execute([$orderId]);
	$o = $s->fetch(PDO::FETCH_ASSOC);
	if ($o) {
		$userId = (int)$o['user_id'];
		$orderNumber = $o['order_number'];
		// Build friendly status text
		$map = [
			'pending' => 'Pendiente',
			'confirmed' => 'Confirmado',
			'processing' => 'Procesando',
			'shipped' => 'Enviado',
			'delivered' => 'Entregado',
			'cancelled' => 'Cancelado'
		];
		$title = 'Estado de pedido actualizado';
		$msg = 'Tu pedido ' . $orderNumber . ' ahora está: ' . ($map[$status] ?? $status) . '.';
		$link = 'profile.php';
		// Create notification
		try {
			$n = $pdo->prepare('INSERT INTO notifications (user_id, title, message, link, is_read) VALUES (?, ?, ?, ?, 0)');
			$n->execute([$userId, $title, $msg, $link]);
		} catch (Exception $e) { /* ignore */ }
	}
	$pdo->commit();
	echo json_encode(['success' => true]);
} catch (Exception $e) {
	if ($pdo->inTransaction()) $pdo->rollBack();
	echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

