<?php
// Debug temporal: listar usuarios y primera parte del hash para diagnóstico.
// BORRAR tras usar.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/db.php';

$out = [];
try {
    $stmt = $pdo->query('SELECT id, email, role, LEFT(password,20) AS hash_prefix FROM users ORDER BY id ASC LIMIT 100');
    $out = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    exit;
}

echo json_encode(['success'=>true,'count'=>count($out),'users'=>$out]);
?>
