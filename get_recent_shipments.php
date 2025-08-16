<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

header('Content-Type: application/json');

$sortDirection = isset($_GET['sort']) && $_GET['sort'] === 'ASC' ? 'ASC' : 'DESC';

try {
    $shipments = getRecentUndeliveredShipments($pdo, $sortDirection);
    
    // Agregar el badge de estado a cada envío
    foreach ($shipments['shipments'] as &$shipment) {
        $shipment['status_badge'] = getStatusBadge($shipment['status']);
        // Calcular el saldo pendiente
        $shipment['balance'] = $shipment['sale_price'] - $shipment['advance_payment'];
    }
    
    echo json_encode($shipments);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener los envíos']);
}
