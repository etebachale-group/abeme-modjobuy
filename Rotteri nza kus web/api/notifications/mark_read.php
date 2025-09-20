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
$ids = $input['ids'] ?? null; // array of ids or 'all'
$read = isset($input['read']) ? (int)!!$input['read'] : 1;

try {
    if ($ids === 'all') {
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = ? WHERE user_id = ?');
        $stmt->execute([$read, $userId]);
        echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
        exit;
    }
    if (!is_array($ids) || empty($ids)) {
        echo json_encode(['success' => false, 'message' => 'No notification ids provided']);
        exit;
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$read, $userId], $ids);
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = ? WHERE user_id = ? AND id IN ($placeholders)");
    $stmt->execute($params);
    echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
