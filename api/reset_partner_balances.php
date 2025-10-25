<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/partner_earnings.php';

header('Content-Type: application/json');
requireAuthApi();
requireSuperAdminApi();

$scope = $_POST['scope'] ?? 'all'; // 'all' or 'partners_only'
// partners_only excludes CAJA and FONDOS DE SOCIOS from wallet reset
$exclude = ($scope === 'partners_only') ? ['CAJA','FONDOS DE SOCIOS'] : [];

try {
  $pdo->beginTransaction();

  // Ensure wallet structures exist
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

  // Get all partners
  $partners = $pdo->query("SELECT partner_name FROM partner_benefits")->fetchAll(PDO::FETCH_COLUMN) ?: [];
  $partnersToReset = array_values(array_filter($partners, function($p) use ($exclude){ return !in_array($p, $exclude, true); }));

  // 1) Zero out wallet balances
  if ($partnersToReset) {
    $in = str_repeat('?,', count($partnersToReset)-1) . '?';
    $stmt = $pdo->prepare("UPDATE partner_benefits SET wallet_balance = 0.00 WHERE partner_name IN ($in)");
    $stmt->execute($partnersToReset);

    // 2) Delete wallet transactions for those partners
    $pdo->exec("CREATE TABLE IF NOT EXISTS partner_wallet_transactions (
      id INT AUTO_INCREMENT PRIMARY KEY,
      partner_name VARCHAR(100) NOT NULL,
      type ENUM('deposit','withdraw') NOT NULL,
      amount DECIMAL(15,2) NOT NULL,
      previous_balance DECIMAL(15,2) NOT NULL,
      new_balance DECIMAL(15,2) NOT NULL,
      notes TEXT,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_partner_name (partner_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $pdo->prepare("DELETE FROM partner_wallet_transactions WHERE partner_name IN ($in)");
    $stmt->execute($partnersToReset);

    // 3) Mark partner_payments as not confirmed (they haven't withdrawn)
    $stmt = $pdo->prepare("UPDATE partner_payments SET confirmed = 0 WHERE partner_name IN ($in)");
    $stmt->execute($partnersToReset);
  }

  // 4) Recompute benefits current_balance for each partner to reflect zero withdrawals
  foreach ($partnersToReset as $p) {
    updatePartnerBenefits($p);
  }

  if ($pdo->inTransaction()) { $pdo->commit(); }
  echo json_encode(['success' => true, 'message' => 'Saldos de monederos reiniciados y retiros deshechos']);
} catch (Exception $e) {
  if (method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) { $pdo->rollBack(); }
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
