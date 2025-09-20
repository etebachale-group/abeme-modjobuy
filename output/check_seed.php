<?php
$email = isset($argv[1]) ? $argv[1] : '';
if ($email === '') { echo "NOEMAIL"; exit; }
require_once __DIR__ . '/../includes/db.php';
$st = $pdo->prepare('SELECT u.id uid, u.email, a.id aid FROM users u LEFT JOIN admins a ON a.user_id=u.id WHERE u.email = ?');
$st->execute([$email]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) { echo "USER=0"; exit; }
$aid = (int)($row['aid'] ?? 0);
$pc = 0;
if ($aid > 0) {
  try { $s2 = $pdo->prepare('SELECT COUNT(*) c FROM products WHERE admin_id = ?'); $s2->execute([$aid]); $pc = (int)$s2->fetchColumn(); } catch (Exception $e) {}
}
echo "USER=1;AID=$aid;PCOUNT=$pc;\n";
