<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireAdmin();

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // for nginx if present

// Simple heartbeat SSE stream for now; can be extended to check DB changes
$admin_id = getCurrentAdminId($pdo);
if (!$admin_id) { http_response_code(403); exit; }

$last = 0;
while (true) {
    echo "event: ping\n";
    echo 'data: {"t":' . time() . "}\n\n";
    @ob_flush(); @flush();
    if (connection_aborted()) break;
    sleep(10);
}
?>
