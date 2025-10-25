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

$name = trim($_POST['name'] ?? '');
if ($name === '') {
  echo json_encode(['success' => false, 'message' => 'Nombre requerido']);
  exit;
}

try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

  $stmt = $pdo->prepare('INSERT INTO categories (name) VALUES (?)');
  $stmt->execute([$name]);
  echo json_encode(['success' => true]);
} catch (PDOException $e) {
  $msg = stripos($e->getMessage(), 'duplicate') !== false ? 'La categoría ya existe' : 'Error: '.$e->getMessage();
  echo json_encode(['success' => false, 'message' => $msg]);
}
