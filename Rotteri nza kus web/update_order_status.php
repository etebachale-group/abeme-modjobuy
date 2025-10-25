<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

// Enforce admin API access and method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
	exit;
}
if (!function_exists('requireAdminApi')) {
	if (!isAuthenticated() || !isAdmin()) {
		http_response_code(403);
		echo json_encode(['success' => false, 'message' => 'No autorizado']);
		exit;
	}
} else {
	requireAdminApi();
}

// CSRF protection
if (!function_exists('csrf_validate') || !csrf_validate($_POST['csrf_token'] ?? '')) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
	exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
$status = trim($_POST['status'] ?? '');
$allowed = ['pending','confirmed','processing','shipped','delivered','cancelled'];
if ($orderId <= 0 || !in_array($status, $allowed, true)) {
	echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
	exit;
}

try {
	$pdo->beginTransaction();

	// Ensure admin owns at least one item of this order
	$admin_id = function_exists('getCurrentAdminId') ? getCurrentAdminId($pdo) : null;
	if (!$admin_id) { throw new Exception('Admin no válido'); }
	$chk = $pdo->prepare("SELECT COUNT(*) AS cnt FROM order_items oi WHERE oi.order_id = ? AND oi.product_id IN (SELECT id FROM products WHERE admin_id = ?)");
	$chk->execute([$orderId, $admin_id]);
	$c = (int)($chk->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);
	if ($c === 0) {
		throw new Exception('No autorizado para este pedido');
	}

	// Update order
	$u = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
	$u->execute([$status, $orderId]);

	// Notify user
	$s = $pdo->prepare('SELECT user_id, order_number FROM orders WHERE id = ?');
	$s->execute([$orderId]);
	if ($o = $s->fetch(PDO::FETCH_ASSOC)) {
		$map = [
			'pending' => 'Pendiente',
			'confirmed' => 'Confirmado',
			'processing' => 'Procesando',
			'shipped' => 'Enviado',
			'delivered' => 'Entregado',
			'cancelled' => 'Cancelado'
		];
		try {
			$n = $pdo->prepare('INSERT INTO notifications (user_id, title, message, link, is_read) VALUES (?, ?, ?, ?, 0)');
			$n->execute([
				(int)$o['user_id'],
				'Estado de pedido actualizado',
				'Tu pedido ' . $o['order_number'] . ' ahora está: ' . ($map[$status] ?? $status) . '.',
				'profile.php'
			]);
		} catch (Exception $ignore) {}
	}

	$pdo->commit();
	echo json_encode(['success' => true]);
} catch (Exception $e) {
	if ($pdo->inTransaction()) $pdo->rollBack();
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


