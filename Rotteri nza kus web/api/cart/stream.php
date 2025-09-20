<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

if (!isAuthenticated()) {
    http_response_code(401);
    exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
@ob_end_flush();
@ob_implicit_flush(true);
set_time_limit(0);

$userId = currentUserId();
$lastCount = -1;
$maxSeconds = 300; // 5 minutes keep-alive
$start = time();

function sse_send($event, $data) {
    if ($event) echo "event: {$event}\n";
    echo 'data: ' . json_encode($data) . "\n\n";
}

while (!connection_aborted() && (time() - $start) < $maxSeconds) {
    try {
        $st = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) as c FROM cart WHERE user_id = ?');
        $st->execute([$userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: ['c'=>0];
        $count = (int)($row['c'] ?? 0);
        if ($count !== $lastCount) {
            sse_send('count', ['count' => $count]);
            $lastCount = $count;
        } else {
            sse_send('ping', ['ts'=>time()]);
        }
    } catch (Exception $e) {
        sse_send('error', ['message' => 'cart stream error']);
    }
    @flush();
    @ob_flush();
    sleep(5);
}

sse_send('end', ['reason' => 'timeout']);
@flush();
