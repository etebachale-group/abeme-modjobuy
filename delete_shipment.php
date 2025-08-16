<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Requerir autenticación para esta acción
requireAuth();

header('Content-Type: application/json');

// Obtener el cuerpo de la solicitud JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'ID de envío no válido.']);
    exit;
}

$id = $input['id'];

if (deleteShipment($pdo, $id)) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'Error al eliminar el envío de la base de datos.']);
}
