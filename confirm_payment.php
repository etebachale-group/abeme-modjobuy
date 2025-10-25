<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/partner_earnings.php';
require_once 'includes/wallet.php';

header('Content-Type: application/json');

// Ensure the user is authenticated
requireAuth();
// Only super_admin can confirm and deposit payments into wallets
$role = $_SESSION['role'] ?? 'user';
if ($role !== 'super_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo super admin puede confirmar pagos.']);
    exit;
}

// Small helper to ensure partner_payments has the expected structure
function ensurePartnerPaymentsTable($pdo) {
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_name VARCHAR(100) NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        confirmation_date TIMESTAMP NULL,
        confirmed BOOLEAN DEFAULT FALSE,
        notes TEXT
    )");

    // Fetch existing columns
    $colsStmt = $pdo->query("DESCRIBE partner_payments");
    $cols = method_exists($colsStmt, 'fetchAll') ? $colsStmt->fetchAll() : [];
    $colNames = [];
    foreach ($cols as $row) {
        if (isset($row['Field'])) $colNames[] = $row['Field'];
    }

    // Add missing columns
    if (!in_array('previous_balance', $colNames)) {
        $pdo->exec("ALTER TABLE partner_payments ADD COLUMN previous_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00");
    }
    if (!in_array('new_balance', $colNames)) {
        $pdo->exec("ALTER TABLE partner_payments ADD COLUMN new_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00");
    }
        if (!in_array('payment_type', $colNames)) {
            $pdo->exec("ALTER TABLE partner_payments ADD COLUMN payment_type VARCHAR(32) NULL");
        }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['partner_name']) && isset($_POST['amount_paid'])) {
    $partnerName = $_POST['partner_name'];
    $amountPaid = floatval($_POST['amount_paid']);
    $notes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : '';

    try {
        // Ensure table and required columns exist
        ensurePartnerPaymentsTable($pdo);

        // Preflight: ensure wallet structures exist BEFORE starting a transaction
        try {
            // Ensure partner row exists (percentage may be 0 by default)
            $stmt = $pdo->prepare("INSERT IGNORE INTO partner_benefits (partner_name, percentage) VALUES (?, ?)");
            $stmt->execute([$partnerName, 0.00]);
        } catch (Exception $ignored) {}

        // Ensure wallet_balance column exists
        try {
            $cols = $pdo->query("DESCRIBE partner_benefits")->fetchAll(PDO::FETCH_COLUMN);
            if ($cols && !in_array('wallet_balance', $cols, true)) {
                $pdo->exec("ALTER TABLE partner_benefits ADD COLUMN wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00");
            }
        } catch (Exception $ignored) {}

        // Ensure wallet transactions log table exists
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
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (Exception $ignored) {}

    // Start a transaction for atomicity
    $pdo->beginTransaction();

        // 1. Actualizar los beneficios del socio para tener los valores más recientes
        $updateResult = updatePartnerBenefits($partnerName);
        if (!$updateResult['success']) {
            throw new Exception("Error al actualizar beneficios: " . ($updateResult['error'] ?? 'desconocido'));
        }

        // 2. Obtener el balance actual
        $stmt = $pdo->prepare("SELECT current_balance FROM partner_benefits WHERE partner_name = ?");
        $stmt->execute([$partnerName]);
        $currentBalance = $stmt->fetchColumn();

        if ($currentBalance === false || $currentBalance === null) {
            throw new Exception("No se encontró al socio.");
        }

        if ($currentBalance < $amountPaid) {
            throw new Exception("El balance actual es insuficiente para este pago.");
        }

        // Calcular el nuevo balance teórico resultante de confirmar el pago
        $newBalance = $currentBalance - $amountPaid;

    // 3. No modificar current_balance manualmente; se actualizará por la inserción del pago confirmado

        // Check columns present to build appropriate INSERT
        $colsStmt = $pdo->query("DESCRIBE partner_payments");
        $cols = $colsStmt->fetchAll();
        $colNames = [];
        foreach ($cols as $row) { if (isset($row['Field'])) $colNames[] = $row['Field']; }
        $hasPrev = in_array('previous_balance', $colNames);
        $hasNew = in_array('new_balance', $colNames);

    if ($hasPrev && $hasNew) {
            $stmt = $pdo->prepare("
                INSERT INTO partner_payments (
                    partner_name,
                    amount,
                    payment_date,
                    confirmation_date,
                    confirmed,
                    previous_balance,
                    new_balance,
                    notes
                ) VALUES (?, ?, NOW(), NOW(), TRUE, ?, ?, ?)
            ");
            $stmt->execute([
                $partnerName,
                $amountPaid,
                $currentBalance,
            $newBalance,
            $notes !== '' ? $notes : 'Pago confirmado automáticamente'
            ]);
        } else {
            // Fallback insert without balance columns
            $stmt = $pdo->prepare("
                INSERT INTO partner_payments (
                    partner_name,
                    amount,
                    payment_date,
                    confirmation_date,
                    confirmed,
                    notes
                ) VALUES (?, ?, NOW(), NOW(), TRUE, ?)
            ");
            $stmt->execute([
                $partnerName,
                $amountPaid,
            $notes !== '' ? $notes : 'Pago confirmado automáticamente'
            ]);
        }

            // Set payment_type to distinguish partner payout vs CAJA
            try {
                $ptype = (strcasecmp($partnerName, 'CAJA') === 0) ? 'caja' : 'partner_payout';
                $pdo->prepare("UPDATE partner_payments SET payment_type = ? WHERE id = LAST_INSERT_ID()")->execute([$ptype]);
            } catch (Exception $ignored) {}

        // 4. Acreditar al monedero usando helper central (para todos, incluida CAJA)
        //    El pago ya fue registrado (confirmed), por lo que el saldo pendiente bajará vía recompute;
        //    aquí solo incrementamos el monedero sin volver a exigir saldo pendiente.
        $h = wallet_deposit($partnerName, (float)$amountPaid, 'admin_deposit', ($notes !== '' ? $notes : 'Depósito al monedero (confirmación)'), false, false);
        if (!($h['success'] ?? false)) throw new Exception($h['message'] ?? 'No se pudo acreditar al monedero');

        $pdo->commit(); // Commit the transaction

    echo json_encode(['success' => true, 'message' => 'Pago confirmado y depositado al monedero.']);

    } catch (PDOException $e) {
        if (method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("PDOException in confirm_payment.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error de base de datos al confirmar el pago.']);
    } catch (Exception $e) {
        if (method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("Exception in confirm_payment.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos para confirmar el pago.']);
}
?>