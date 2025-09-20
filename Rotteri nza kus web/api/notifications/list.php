<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'Auth required']);
    exit;
}

$userId = currentUserId();

// Query params: filter=all|unread|read, limit, offset
$filter = $_GET['filter'] ?? 'all';
$limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
$offset = max(0, (int)($_GET['offset'] ?? 0));

$where = 'user_id = :uid';
if ($filter === 'unread') { $where .= ' AND is_read = 0'; }
if ($filter === 'read') { $where .= ' AND is_read = 1'; }

try {
    $stmt = $pdo->prepare("SELECT id, title, message, link, is_read, created_at FROM notifications WHERE $where ORDER BY created_at DESC LIMIT :lim OFFSET :off");
    $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $st = $pdo->prepare('SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0');
    $st->execute([$userId]);
    $cu = (int)($st->fetch(PDO::FETCH_ASSOC)['c'] ?? 0);

    echo json_encode(['success' => true, 'notifications' => $rows, 'unread' => $cu]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
