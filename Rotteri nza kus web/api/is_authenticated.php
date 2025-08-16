<?php
require_once '../includes/auth.php';

header('Content-Type: application/json');

echo json_encode(['authenticated' => isAuthenticated()]);
?>