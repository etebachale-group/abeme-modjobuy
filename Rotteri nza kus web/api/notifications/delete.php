<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

if (!isAuthenticated()) {
    echo json_encode(['success' => false, 'message' => 'Auth required']);
    exit;
}

$userId = currentUserId();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$ids = $input['ids'] ?? null; // array of ids or 'all-read' or 'all'

try {
    if ($ids === 'all') {
        $stmt = $pdo->prepare('DELETE FROM notifications WHERE user_id = ?');
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
        exit;
    }
    if ($ids === 'all-read') {
        $stmt = $pdo->prepare('DELETE FROM notifications WHERE user_id = ? AND is_read = 1');
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
        exit;
    }
    if (!is_array($ids) || empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No notification ids provided']);
        exit;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$userId], $ids);
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND id IN ($placeholders)");
    $stmt->execute($params);
    echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
