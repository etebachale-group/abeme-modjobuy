<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
header('Content-Type: application/json');
requireAdminApi();

if (!csrf_validate($_POST['csrf_token'] ?? '')) {
  http_response_code(403);
  echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
  exit;
}

$admin_id = getCurrentAdminId($pdo);
$id = (int)($_POST['product_id'] ?? 0);
$tags = trim($_POST['tags'] ?? '');

if ($id <= 0) {
  echo json_encode(['success' => false, 'message' => 'ID inválido']);
  exit;
}

try {
  // ensure tags column exists
  try { $pdo->exec("ALTER TABLE products ADD COLUMN tags VARCHAR(500) NULL"); } catch (Exception $ignore) {}

  // Verify ownership
  $stmt = $pdo->prepare('SELECT admin_id FROM products WHERE id = ?');
  $stmt->execute([$id]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row || (!empty($row['admin_id']) && (int)$row['admin_id'] !== (int)$admin_id)) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
  }

  $stmt = $pdo->prepare('UPDATE products SET tags = ? WHERE id = ?');
  $stmt->execute([$tags, $id]);
  echo json_encode(['success' => true]);
} catch (PDOException $e) {
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
