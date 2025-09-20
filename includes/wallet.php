<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/partner_earnings.php';

// Ensure partner_payments table and expected columns exist (avoid DDL inside active TX)
function wallet_ensure_partner_payments_table($pdo) {
    // Create base table
    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        partner_name VARCHAR(100) NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        confirmation_date TIMESTAMP NULL,
        confirmed BOOLEAN DEFAULT FALSE,
        notes TEXT
    )");
    // Add missing columns if needed
    try {
        $colsStmt = $pdo->query("DESCRIBE partner_payments");
        $cols = method_exists($colsStmt, 'fetchAll') ? $colsStmt->fetchAll() : [];
        $colNames = [];
        foreach ($cols as $row) { if (isset($row['Field'])) $colNames[] = $row['Field']; }
        if (!in_array('previous_balance', $colNames)) {
            $pdo->exec("ALTER TABLE partner_payments ADD COLUMN previous_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00");
        }
        if (!in_array('new_balance', $colNames)) {
            $pdo->exec("ALTER TABLE partner_payments ADD COLUMN new_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00");
        }
        if (!in_array('payment_type', $colNames)) {
            $pdo->exec("ALTER TABLE partner_payments ADD COLUMN payment_type VARCHAR(32) NULL");
        }
    } catch (Exception $ignored) {}
}

/**
 * Deposit to wallet with full consistency:
 * - Recompute benefits
 * - Lock row, validate pending/current_balance when fromPending=true
 * - Update wallet_balance
 * - Log to partner_wallet_transactions with method
 * - Optionally insert a partner_payments record (when treating as payout/confirmation)
 */
function wallet_deposit(string $partnerName, float $amount, string $method = 'admin_deposit', string $notes = '', bool $asPayment = true, bool $fromPending = true): array {
    global $pdo;
    if ($amount <= 0) return ['success' => false, 'message' => 'Monto inválido'];

    // Ensure structures (avoid DDL inside active transactions)
    try {
        $inTxNow = method_exists($pdo, 'inTransaction') && $pdo->inTransaction();
        if (!$inTxNow) {
            // Ensure wallet_balance column
            try {
                $cols = $pdo->query("DESCRIBE partner_benefits")->fetchAll(PDO::FETCH_COLUMN);
                if ($cols && !in_array('wallet_balance', $cols)) {
                    $pdo->exec("ALTER TABLE partner_benefits ADD COLUMN wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00");
                }
            } catch (Exception $ignored) {}
            // Ensure transactions table and 'method' column
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
                try {
                    $twCols = $pdo->query("DESCRIBE partner_wallet_transactions")->fetchAll(PDO::FETCH_COLUMN);
                    if ($twCols && !in_array('method', $twCols, true)) {
                        $pdo->exec("ALTER TABLE partner_wallet_transactions ADD COLUMN method VARCHAR(32) NULL AFTER new_balance");
                    }
                } catch (Exception $ignored) {}
            } catch (Exception $ignored) {}
        }
    } catch (Exception $ignored) {}

    // Ensure partner row exists (avoid updatePartnerBenefits failure)
    try { $stmt = $pdo->prepare("INSERT IGNORE INTO partner_benefits (partner_name, percentage) VALUES (?, ?)"); $stmt->execute([$partnerName, 0.00]); } catch (Exception $ignored) {}

    // Ensure payments table (before TX)
    if ($asPayment) { wallet_ensure_partner_payments_table($pdo); }

    try {
        $startedTx = (method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) ? false : true;
        if ($startedTx) { $pdo->beginTransaction(); }
        // Refresh aggregates
        $upd = updatePartnerBenefits($partnerName);
        if (!$upd['success']) throw new Exception('No se pudo actualizar beneficios');

        // Lock row and read
        $stmt = $pdo->prepare("SELECT current_balance, wallet_balance FROM partner_benefits WHERE partner_name = ? FOR UPDATE");
        $stmt->execute([$partnerName]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception('Socio no encontrado');
        $current = (float)$row['current_balance'];
        $wallet  = (float)$row['wallet_balance'];

        if ($fromPending && $current < $amount) throw new Exception('Saldo pendiente insuficiente para depositar');

        // Optionally record a payment (so current_balance is reduced by payouts consistently)
    if ($asPayment) {
            $stmt = $pdo->prepare("INSERT INTO partner_payments (partner_name, amount, payment_date, confirmation_date, confirmed, previous_balance, new_balance, notes) VALUES (?, ?, NOW(), NOW(), 1, ?, ?, ?)");
            $stmt->execute([$partnerName, $amount, $current, max(0, $current - $amount), ($notes !== '' ? $notes : 'Depósito al monedero')]);
            try { $pdo->prepare("UPDATE partner_payments SET payment_type = ? WHERE id = LAST_INSERT_ID()")->execute(['wallet_deposit']); } catch (Exception $ignored) {}
        }

        $prev = $wallet;
        $next = $wallet + $amount;
        $pdo->prepare("UPDATE partner_benefits SET wallet_balance = ? WHERE partner_name = ?")->execute([$next, $partnerName]);
        $pdo->prepare("INSERT INTO partner_wallet_transactions (partner_name, type, amount, previous_balance, new_balance, method, notes) VALUES (?, 'deposit', ?, ?, ?, ?, ?)")
            ->execute([$partnerName, $amount, $prev, $next, $method, $notes]);

        // Recompute to reflect pending deduction when applicable
        updatePartnerBenefits($partnerName);

    if ($startedTx && method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) { $pdo->commit(); }
        return ['success' => true, 'walletBalance' => $next];
    } catch (Throwable $e) {
    if ($startedTx && method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Withdraw from wallet with locking and logging.
 */
function wallet_withdraw(string $partnerName, float $amount, string $method = 'partner_withdraw', string $notes = ''): array {
    global $pdo;
    if ($amount <= 0) return ['success' => false, 'message' => 'Monto inválido'];

    // Ensure partner row exists and required structures (avoid DDL inside active transaction)
    try { $stmt = $pdo->prepare("INSERT IGNORE INTO partner_benefits (partner_name, percentage) VALUES (?, ?)"); $stmt->execute([$partnerName, 0.00]); } catch (Exception $ignored) {}
    try {
        $inTxNow = method_exists($pdo, 'inTransaction') && $pdo->inTransaction();
        if (!$inTxNow) {
            // Ensure wallet_balance column exists
            try {
                $cols = $pdo->query("DESCRIBE partner_benefits")->fetchAll(PDO::FETCH_COLUMN);
                if ($cols && !in_array('wallet_balance', $cols, true)) {
                    $pdo->exec("ALTER TABLE partner_benefits ADD COLUMN wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00");
                }
            } catch (Exception $ignored) {}
        }
    } catch (Exception $ignored) {}
    try {
        $inTxNow = method_exists($pdo, 'inTransaction') && $pdo->inTransaction();
        if (!$inTxNow) {
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
            try {
                $twCols2 = $pdo->query("DESCRIBE partner_wallet_transactions")->fetchAll(PDO::FETCH_COLUMN);
                if ($twCols2 && !in_array('method', $twCols2, true)) {
                    $pdo->exec("ALTER TABLE partner_wallet_transactions ADD COLUMN method VARCHAR(32) NULL AFTER new_balance");
                }
            } catch (Exception $ignored) {}
        }
    } catch (Exception $ignored) {}

    try {
        $startedTx = (method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) ? false : true;
        if ($startedTx) { $pdo->beginTransaction(); }
        $stmt = $pdo->prepare("SELECT wallet_balance FROM partner_benefits WHERE partner_name = ? FOR UPDATE");
        $stmt->execute([$partnerName]);
        $wallet = $stmt->fetchColumn();
        if ($wallet === false) throw new Exception('Socio no encontrado');
        $wallet = (float)$wallet;
        if ($wallet < $amount) throw new Exception('Saldo en monedero insuficiente');
        $prev = $wallet; $next = $wallet - $amount;
        $pdo->prepare("UPDATE partner_benefits SET wallet_balance = ? WHERE partner_name = ?")->execute([$next, $partnerName]);
        $pdo->prepare("INSERT INTO partner_wallet_transactions (partner_name, type, amount, previous_balance, new_balance, method, notes) VALUES (?, 'withdraw', ?, ?, ?, ?, ?)")
            ->execute([$partnerName, $amount, $prev, $next, $method, $notes]);
        $txId = null;
        try { $txId = $pdo->lastInsertId(); } catch (Exception $ignored) { $txId = null; }
    if ($startedTx && method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) { $pdo->commit(); }
        return ['success' => true, 'walletBalance' => $next, 'transaction_id' => $txId, 'previous_balance' => $prev, 'new_balance' => $next];
    } catch (Throwable $e) {
    if ($startedTx && method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
