<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/partner_earnings.php';

header('Content-Type: application/json');
requireAuthApi();

$partner = isset($_GET['partner']) ? trim($_GET['partner']) : '';
if ($partner === '') {
  echo json_encode(['success' => false, 'message' => 'Socio no especificado']);
  exit;
}

// Access control
if (strcasecmp($partner, 'CAJA') === 0) {
  // Only super admin can access CAJA balances
  if (function_exists('requireSuperAdminApi')) {
    requireSuperAdminApi();
  } else {
    $role = $_SESSION['role'] ?? 'user';
    if ($role !== 'super_admin') {
      http_response_code(403);
      echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
      exit;
    }
  }
} else {
  // Partners: only owner or super_admin
  if (function_exists('requirePartnerAccessApi')) {
    requirePartnerAccessApi($partner);
  } elseif (function_exists('requirePartnerAccess')) {
    requirePartnerAccess($partner);
  }
}

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
  try { $pdo->exec("ALTER TABLE partner_benefits ADD COLUMN IF NOT EXISTS wallet_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00"); } catch (Exception $e) {}

  // Recompute balances
  $upd = updatePartnerBenefits($partner);
  if (!$upd || !$upd['success']) {
    throw new Exception('No se pudo actualizar beneficios');
  }
  // Fetch wallet balance
  $stmt = $pdo->prepare('SELECT percentage, total_earnings, current_balance, COALESCE(wallet_balance,0) AS wallet_balance FROM partner_benefits WHERE partner_name = ?');
  $stmt->execute([$partner]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) throw new Exception('Socio no encontrado');

  echo json_encode(['success' => true, 'data' => $row]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
