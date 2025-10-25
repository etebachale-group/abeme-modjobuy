<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireAuth();

$partner = $_GET['partner'] ?? '';
if ($partner === '') {
  http_response_code(400);
  echo 'Socio requerido';
  exit;
}

$f_from = $_GET['from'] ?? '';
$f_to = $_GET['to'] ?? '';
$f_type = $_GET['type'] ?? '';
$f_min = $_GET['min'] ?? '';
$f_max = $_GET['max'] ?? '';
$f_q = $_GET['q'] ?? '';

$where = ["partner_name = ?"]; $params = [$partner];
if ($f_from) { $where[] = 'created_at >= ?'; $params[] = $f_from . ' 00:00:00'; }
if ($f_to)   { $where[] = 'created_at <= ?'; $params[] = $f_to . ' 23:59:59'; }
if ($f_type === 'deposit' || $f_type === 'withdraw') { $where[] = 'type = ?'; $params[] = $f_type; }
if ($f_min !== '' && is_numeric($f_min)) { $where[] = 'amount >= ?'; $params[] = (float)$f_min; }
if ($f_max !== '' && is_numeric($f_max)) { $where[] = 'amount <= ?'; $params[] = (float)$f_max; }
if ($f_q !== '') { $where[] = '(notes LIKE ?)'; $params[] = '%' . $f_q . '%'; }

$sql = 'SELECT created_at, type, method, amount, previous_balance, new_balance, notes FROM partner_wallet_transactions WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'monedero_' . preg_replace('/\s+/', '_', $partner) . '_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$out = fopen('php://output', 'w');
// BOM for UTF-8
fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, ['Fecha','Tipo','Método','Monto','Balance Anterior','Nuevo Balance','Notas']);
foreach ($rows as $r) {
  fputcsv($out, [
    $r['created_at'],
    $r['type'] === 'withdraw' ? 'Retiro' : 'Depósito',
  $r['method'] ?? '',
    number_format((float)$r['amount'], 2, '.', ''),
    number_format((float)$r['previous_balance'], 2, '.', ''),
    number_format((float)$r['new_balance'], 2, '.', ''),
    $r['notes']
  ]);
}

fclose($out);
exit;
