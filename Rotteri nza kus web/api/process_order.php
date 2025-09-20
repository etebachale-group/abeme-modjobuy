<?php
// Backward-compatibility wrapper to create an order from the user's cart
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
	echo json_encode(['success' => false, 'message' => 'Authentication required.']);
	exit;
}

// Delegate to create_order.php logic by including it or reusing function
require __DIR__ . '/create_order.php';
