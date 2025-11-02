<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireAuth();

$partnerName = 'CAJA';

// Leer filtros
$qFrom = $_GET['from'] ?? '';
$qTo = $_GET['to'] ?? '';
$qStatus = $_GET['status'] ?? '';
$qMin = $_GET['min'] ?? '';
$qMax = $_GET['max'] ?? '';
$qText = $_GET['q'] ?? '';
$qPayType = $_GET['payment_type'] ?? '';

$where = ["partner_name = ?"];
$params = [$partnerName];
if ($qFrom) { $where[] = "payment_date >= ?"; $params[] = $qFrom . ' 00:00:00'; }
if ($qTo)   { $where[] = "payment_date <= ?"; $params[] = $qTo . ' 23:59:59'; }
if ($qStatus !== '' && ($qStatus === '0' || $qStatus === '1')) { $where[] = "confirmed = ?"; $params[] = (int)$qStatus; }
if ($qMin !== '' && is_numeric($qMin)) { $where[] = "amount >= ?"; $params[] = (float)$qMin; }
if ($qMax !== '' && is_numeric($qMax)) { $where[] = "amount <= ?"; $params[] = (float)$qMax; }
if ($qText !== '') { $where[] = "(notes LIKE ? )"; $params[] = '%' . $qText . '%'; }
if ($qPayType !== '') { $where[] = "(payment_type = ?)"; $params[] = $qPayType; }

$sql = "SELECT payment_date, payment_type, amount, confirmed, previous_balance, new_balance, notes FROM partner_payments WHERE " . implode(' AND ', $where) . " ORDER BY payment_date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'caja_movimientos_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$out = fopen('php://output', 'w');
// BOM for UTF-8
fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($out, ['Fecha','Tipo','Monto','Estado','Balance Anterior','Nuevo Balance','Notas']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['payment_date'],
    $r['payment_type'] ?? '',
        number_format((float)$r['amount'], 2, '.', ''),
        ((int)$r['confirmed']) ? 'Confirmado' : 'Pendiente',
        number_format((float)$r['previous_balance'], 2, '.', ''),
        number_format((float)$r['new_balance'], 2, '.', ''),
        $r['notes']
    ]);
}

fclose($out);
exit;
