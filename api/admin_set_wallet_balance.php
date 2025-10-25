<?php
// Admin tool to set a partner's wallet balance to a target amount.
// Behavior:
// - Moves from current_balance (Pendiente) to wallet first (method: admin_adjust_from_pending)
// - If still below target and external_if_needed=1, tops up directly (method: admin_adjust_external)
// - If above target, withdraws the difference (method: admin_adjust)
// All operations are logged in partner_wallet_transactions with previous/new balances.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/partner_earnings.php';
require_once __DIR__ . '/../includes/wallet.php';

header('Content-Type: application/json; charset=utf-8');

try {
    requireAuthApi();
    $role = $_SESSION['role'] ?? 'user';
    $isSuper = ($role === 'super_admin');
    if (!$isSuper) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
        exit;
    }

    // Solo POST
    $partnerName = isset($_POST['partner_name']) ? trim((string)$_POST['partner_name']) : '';
    if ($partnerName === '') {
        throw new Exception('Falta partner_name');
    }
    // super_admin puede ajustar cualquier monedero; admins no tienen acceso

    $targetStr = $_POST['target'] ?? null;
    if ($targetStr === null || $targetStr === '') {
        throw new Exception('Falta target');
    }
    $target = round((float)$targetStr, 2);
    if ($target < 0) $target = 0.0;

    $externalIfNeeded = isset($_POST['external_if_needed']) ? (int)$_POST['external_if_needed'] : 1;
    $notes = trim((string)($_POST['notes'] ?? ''));

    // Ensure base structures exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_benefits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_name VARCHAR(100) NOT NULL UNIQUE,
        percentage DECIMAL(5,2) NOT NULL,
        total_earnings DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        current_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    try {
        $pdo->exec("ALTER TABLE partner_benefits ADD COLUMN IF NOT EXISTS wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00");
    } catch (Exception $e) {
        try {
            $cols = $pdo->query("DESCRIBE partner_benefits")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('wallet_balance', $cols)) {
                $pdo->exec("ALTER TABLE partner_benefits ADD COLUMN wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00");
            }
        } catch (Exception $ignored) {}
    }

    // partner_wallet_transactions lo aseguran los helpers de wallet

    // Ensure row exists
    $stmt = $pdo->prepare("INSERT IGNORE INTO partner_benefits (partner_name, percentage) VALUES (?, ?)");
    $stmt->execute([$partnerName, 0.00]);

    // Recompute benefits to get the latest current_balance
    updatePartnerBenefits($partnerName);

    $stmt = $pdo->prepare("SELECT current_balance, wallet_balance FROM partner_benefits WHERE partner_name = ?");
    $stmt->execute([$partnerName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception('No se encontró el socio');
    }
    $current = (float)$row['current_balance'];
    $wallet = (float)$row['wallet_balance'];
    $delta = round($target - $wallet, 2);

    if (abs($delta) < 0.005) {
        echo json_encode(['success' => true, 'message' => 'Saldo ya coincide con el objetivo', 'data' => [
            'partner_name' => $partnerName,
            'wallet_balance' => $wallet,
            'current_balance' => $current,
            'target' => $target
        ]]);
        exit;
    }

    // Ajuste usando helpers centralizados para mantener una lógica única

    if ($delta > 0) {
        // Primero mover desde pendiente
        $move = min($delta, $current);
        if ($move > 0) {
            $r1 = wallet_deposit($partnerName, $move, 'admin_adjust_from_pending', trim('Ajuste desde pendiente | ' . $notes . ' | admin_set_wallet_balance'), true, true);
            if (!($r1['success'] ?? false)) { throw new Exception($r1['message'] ?? 'No se pudo ajustar desde pendiente'); }
            $delta = round($delta - $move, 2);
        }
        // Si falta, ajustar externamente si está permitido
        if ($delta > 0 && $externalIfNeeded) {
            $r2 = wallet_deposit($partnerName, $delta, 'admin_adjust_external', trim('Ajuste externo | ' . $notes . ' | admin_set_wallet_balance'), false, false);
            if (!($r2['success'] ?? false)) { throw new Exception($r2['message'] ?? 'No se pudo ajustar externamente'); }
            $delta = 0.0;
        }
    } elseif ($delta < 0) {
        // Exceso: retirar del monedero
        $withdraw = -$delta;
        if ($withdraw > 0) {
            $r3 = wallet_withdraw($partnerName, $withdraw, 'admin_adjust', trim('Ajuste negativo | ' . $notes . ' | admin_set_wallet_balance'));
            if (!($r3['success'] ?? false)) { throw new Exception($r3['message'] ?? 'No se pudo retirar para ajustar'); }
        }
    }

    // Recompute aggregates and return fresh values
    updatePartnerBenefits($partnerName);
    $stmt = $pdo->prepare("SELECT percentage, total_earnings, current_balance, wallet_balance FROM partner_benefits WHERE partner_name = ?");
    $stmt->execute([$partnerName]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'success' => true,
        'message' => 'Saldo de monedero ajustado',
        'data' => [
            'partner_name' => $partnerName,
            'target' => $target,
            'percentage' => isset($row['percentage']) ? (float)$row['percentage'] : 0,
            'total_earnings' => isset($row['total_earnings']) ? (float)$row['total_earnings'] : 0,
            'current_balance' => isset($row['current_balance']) ? (float)$row['current_balance'] : 0,
            'wallet_balance' => isset($row['wallet_balance']) ? (float)$row['wallet_balance'] : 0
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>
