<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');
requireAuthApi();
requireSuperAdminApi();

$partner = isset($_POST['partner_name']) ? trim($_POST['partner_name']) : '';

try {
  // Ensure tables exist
  $pdo->exec("CREATE TABLE IF NOT EXISTS partner_benefits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_name VARCHAR(100) NOT NULL UNIQUE,
    percentage DECIMAL(5,2) NOT NULL,
    total_earnings DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    current_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )");
  try { $pdo->exec("ALTER TABLE partner_benefits ADD COLUMN IF NOT EXISTS wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00"); } catch (Exception $e) {
    try { $cols = $pdo->query('DESCRIBE partner_benefits')->fetchAll(PDO::FETCH_COLUMN); if (!in_array('wallet_balance', $cols)) { $pdo->exec("ALTER TABLE partner_benefits ADD COLUMN wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00"); } } catch (Exception $ignored) {}
  }
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

  $pdo->beginTransaction();

  if ($partner !== '') {
    // Single partner cleanup
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM partner_wallet_transactions WHERE partner_name = ? AND type = 'withdraw'");
    $stmt->execute([$partner]);
    $sum = (float)$stmt->fetchColumn();
    if ($sum > 0) {
      // Restore wallet balance
      $pdo->prepare("UPDATE partner_benefits SET wallet_balance = wallet_balance + ? WHERE partner_name = ?")->execute([$sum, $partner]);
      // Remove withdrawals
      $pdo->prepare("DELETE FROM partner_wallet_transactions WHERE partner_name = ? AND type = 'withdraw'")->execute([$partner]);
    }
    $affected = $sum;
  } else {
    // All partners cleanup
    $byPartner = $pdo->query("SELECT partner_name, COALESCE(SUM(amount),0) AS total FROM partner_wallet_transactions WHERE type = 'withdraw' GROUP BY partner_name")->fetchAll(PDO::FETCH_ASSOC);
    $affected = 0.0;
    foreach ($byPartner as $row) {
      $p = $row['partner_name']; $sum = (float)$row['total'];
      if ($sum <= 0) continue;
      $pdo->prepare("UPDATE partner_benefits SET wallet_balance = wallet_balance + ? WHERE partner_name = ?")->execute([$sum, $p]);
      $pdo->prepare("DELETE FROM partner_wallet_transactions WHERE partner_name = ? AND type = 'withdraw'")->execute([$p]);
      $affected += $sum;
    }
  }

  if ($pdo->inTransaction()) { $pdo->commit(); }
  echo json_encode(['success' => true, 'message' => 'Retiros eliminados', 'restored' => $affected]);
} catch (Exception $e) {
  if (method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) { $pdo->rollBack(); }
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
