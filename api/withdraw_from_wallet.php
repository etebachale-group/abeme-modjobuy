<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/wallet.php';

header('Content-Type: application/json');
requireAuthApi();

$partnerName = $_POST['partner_name'] ?? '';
$amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
$notes = trim((string)($_POST['notes'] ?? ''));

if ($partnerName === '' || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

// Enforce access
$role = $_SESSION['role'] ?? 'user';
if (strcasecmp($partnerName, 'CAJA') === 0) {
    // Only super_admin may withdraw from CAJA wallet
    if ($role !== 'super_admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }
} else {
    // owner/admin/super_admin via partner guard
    if (function_exists('requirePartnerAccessApi')) {
        requirePartnerAccessApi($partnerName);
    } elseif (function_exists('requirePartnerAccess')) {
        requirePartnerAccess($partnerName);
    }
}

$method = (strcasecmp($partnerName, 'CAJA') === 0) ? 'admin_withdraw' : 'partner_withdraw';
$res = wallet_withdraw($partnerName, (float)$amount, $method, $notes);
if ($res['success'] ?? false) {
    $out = [
        'success' => true,
        'message' => 'Retiro realizado',
        'walletBalance' => $res['walletBalance']
    ];
    if (!empty($res['transaction_id'])) { $out['transaction_id'] = $res['transaction_id']; }
    echo json_encode($out);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $res['message'] ?? 'No se pudo retirar']);
}
