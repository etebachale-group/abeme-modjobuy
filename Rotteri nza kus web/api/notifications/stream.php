<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!isAuthenticated()) {
    http_response_code(401);
    exit;
}

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
// CORS not needed for same-origin; add if required
@ob_end_flush();
@ob_implicit_flush(true);
set_time_limit(0);

$userId = currentUserId();
$__sse_user_id = $userId; // keep local copy, then free session lock
@session_write_close();
$lastUnread = -1;
$lastId = 0;
$maxSeconds = 300; // keep alive ~5 mins
$start = time();

function sse_send($event, $data) {
    if ($event) echo "event: {$event}\n";
    echo 'data: ' . json_encode($data) . "\n\n";
}

// Initialize last seen values to avoid spurious 'new' event on first connect
try {
    $st0 = $pdo->prepare('SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0');
    $st0->execute([$userId]);
    $row0 = $st0->fetch(PDO::FETCH_ASSOC) ?: ['unread'=>0];
    $lastUnread = (int)($row0['unread'] ?? 0);
    $st1 = $pdo->prepare('SELECT id FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $st1->execute([$userId]);
    $r1 = $st1->fetch(PDO::FETCH_ASSOC);
    $lastId = (int)($r1['id'] ?? 0);
} catch (Exception $e) {
    // ignore init failures
}

while (!connection_aborted() && (time() - $start) < $maxSeconds) {
    try {
        // Unread badge
        $st = $pdo->prepare('SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0');
        $st->execute([$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: ['unread'=>0];
        $unread = (int)($row['unread'] ?? 0);
        if ($unread !== $lastUnread) {
            sse_send('badge', ['unread' => $unread]);
            $lastUnread = $unread;
        }

        // Latest notification
        $stn = $pdo->prepare('SELECT id, title, message, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 1');
        $stn->execute([$userId]);
        $latest = $stn->fetch(PDO::FETCH_ASSOC);
        if ($latest && (int)$latest['id'] > $lastId) {
            sse_send('new', $latest);
            $lastId = (int)$latest['id'];
        }

        if ($unread === $lastUnread && (!$latest || (int)$latest['id'] === $lastId)) {
            // heartbeat
            sse_send('ping', ['ts' => time()]);
        }
    } catch (Exception $e) {
        sse_send('error', ['message' => 'stream error']);
    }

    // Flush buffer
    @flush();
    @ob_flush();
    sleep(5);
}

// graceful end
sse_send('end', ['reason' => 'timeout']);
@flush();
