<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/partner_earnings.php';
require_once '../includes/wallet.php';

header('Content-Type: application/json');
requireAuthApi();

$partnerName = $_POST['partner_name'] ?? '';
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : (isset($_POST['amount_paid']) ? (float)$_POST['amount_paid'] : 0);
$notes = trim((string)($_POST['notes'] ?? ''));

if ($partnerName === '' || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

// Enforce role: only super_admin may deposit to wallets.
$role = $_SESSION['role'] ?? 'user';
if ($role !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo super admin puede depositar al monedero']);
    exit;
}

// Additionally ensure partner context exists/valid, but admins can deposit to any partner
if ($partnerName === '') {
    echo json_encode(['success' => false, 'message' => 'Socio no especificado']);
    exit;
}
// super_admin puede operar cualquier monedero

try {
    // Ensure wallet structures
    $pdo->exec("ALTER TABLE partner_benefits ADD COLUMN IF NOT EXISTS wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00");
} catch (Exception $e) {
    // Compatibility for older MySQL without IF NOT EXISTS on ADD COLUMN
    try {
        $check = $pdo->query("DESCRIBE partner_benefits")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('wallet_balance', $check)) {
            $pdo->exec("ALTER TABLE partner_benefits ADD COLUMN wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00");
        }
    } catch (Exception $ignored) {}
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_wallet_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_name VARCHAR(100) NOT NULL,
        type ENUM('deposit','withdraw') NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        previous_balance DECIMAL(15,2) NOT NULL,
        new_balance DECIMAL(15,2) NOT NULL,
        method VARCHAR(32) NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_partner_name (partner_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

// Use helper to ensure the same logic for all partners
$res = wallet_deposit($partnerName, (float)$amount, 'admin_deposit', $notes, true, true);
if ($res['success'] ?? false) {
    echo json_encode(['success' => true, 'message' => 'Depósito realizado al monedero', 'walletBalance' => $res['walletBalance']]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $res['message'] ?? 'No se pudo depositar']);
}
