<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/partner_earnings.php';
require_once '../includes/wallet.php';

header('Content-Type: application/json');
requireAuthApi();
requireSuperAdminApi();

try {

  // Ensure structures
  $pdo->exec("CREATE TABLE IF NOT EXISTS partner_benefits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    partner_name VARCHAR(100) NOT NULL UNIQUE,
    percentage DECIMAL(5,2) NOT NULL,
    total_earnings DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    current_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )");
  try { $pdo->exec("ALTER TABLE partner_benefits ADD COLUMN IF NOT EXISTS wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00"); } catch (Exception $e) {
    try { $cols = $pdo->query("DESCRIBE partner_benefits")->fetchAll(PDO::FETCH_COLUMN); if (!in_array('wallet_balance', $cols)) { $pdo->exec("ALTER TABLE partner_benefits ADD COLUMN wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00"); } } catch (Exception $ignored) {}
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

  // Get all partners except CAJA (wallet applies to socios)
  $partners = $pdo->query("SELECT partner_name FROM partner_benefits WHERE partner_name <> 'CAJA'")->fetchAll(PDO::FETCH_COLUMN) ?: [];

  foreach ($partners as $p) {
    // Recompute and deposit full pending into wallet using centralized helper
    $u = updatePartnerBenefits($p);
    if (!$u || !$u['success']) continue;
    // Determine up-to-date pending
    $stmt = $pdo->prepare("SELECT current_balance FROM partner_benefits WHERE partner_name = ?");
    $stmt->execute([$p]);
    $pending = (float)($stmt->fetchColumn() ?: 0);
    if ($pending <= 0) continue;
    // Deposit from pending; record as a payment so benefits decrease
    $res = wallet_deposit($p, $pending, 'seed_admin', 'Semilla inicial desde pendiente', true, true);
    if (!($res['success'] ?? false)) {
      // Continue to next partner but aggregate error reporting if desired
      // For now, ignore individual failures and proceed
      continue;
    }
  }

  echo json_encode(['success' => true, 'message' => 'Monederos cargados desde pendientes']);
} catch (Exception $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
