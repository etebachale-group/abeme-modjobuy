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
$name = trim($_POST['name'] ?? '');
if ($id <= 0 || $name === '') {
  echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
  exit;
}

try {
  $stmt = $pdo->prepare('UPDATE categories SET name = ? WHERE id = ?');
  $stmt->execute([$name, $id]);
  echo json_encode(['success' => true]);
} catch (PDOException $e) {
  $msg = stripos($e->getMessage(), 'duplicate') !== false ? 'Ya existe una categoría con ese nombre' : 'Error: '.$e->getMessage();
  echo json_encode(['success' => false, 'message' => $msg]);
}
