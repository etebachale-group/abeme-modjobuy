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

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
  echo json_encode(['success' => false, 'message' => 'ID inválido']);
  exit;
}

try {
  // First, unset category from products that reference it (in case FK isn't present)
  try {
    $stmt = $pdo->prepare('UPDATE products SET category_id = NULL WHERE category_id = ?');
    $stmt->execute([$id]);
  } catch (Exception $ignore) {}

  // Then delete category
  $stmt = $pdo->prepare('DELETE FROM categories WHERE id = ?');
  $stmt->execute([$id]);
  echo json_encode(['success' => true]);
} catch (PDOException $e) {
  echo json_encode(['success' => false, 'message' => 'Error: '.$e->getMessage()]);
}
