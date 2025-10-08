<?php
// Endpoint deshabilitado (mantenimiento eliminado). Devuelve 410.
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success'=>false,'message'=>'repair_admins.php eliminado']);
exit;
