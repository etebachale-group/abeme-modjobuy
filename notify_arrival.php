<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Verificar que el usuario esté autenticado
requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$groupCode = $_POST['groupCode'] ?? '';
$shipDate = $_POST['shipDate'] ?? '';

if (empty($groupCode)) {
    http_response_code(400);
    echo json_encode(['error' => 'Código de grupo requerido']);
    exit;
}

try {
    // Obtener los envíos del grupo
    $shipments = getShipmentsByGroupCode($pdo, $groupCode);
    
    if (empty($shipments)) {
        throw new Exception('No se encontraron envíos para este grupo');
    }

    // Calcular suma total
    $totalAmount = array_reduce($shipments, function($carry, $item) {
        return $carry + $item['sale_price'];
    }, 0);

    // Preparar mensaje para el administrador
    $adminMessage = urlencode("📦 PAQUETES DE " . $groupCode . "\n");
    $adminMessage .= urlencode("--------------------------------\n");
    
    foreach ($shipments as $shipment) {
        $adminMessage .= urlencode("•" . $shipment['receiver_name'] . " " .
                                $shipment['receiver_phone'] . " paga " .
                                number_format($shipment['sale_price'], 0, '.', ',') . " XAF");
        if ($shipment['advance_payment'] > 0) {
            $adminMessage .= urlencode(" (Adelantado: " . number_format($shipment['advance_payment'], 2, '.', ',') . " XAF, Saldo: " . number_format($shipment['sale_price'] - $shipment['advance_payment'], 2, '.', ',') . " XAF)\n");
        } else {
            $adminMessage .= urlencode("\n");
        }
    }
    
    $adminMessage .= urlencode("__________________________________\n");
    $adminMessage .= urlencode("TOTAL: " . number_format($totalAmount, 0, '.', ',') . " XAF");

    // Preparar mensaje para el grupo público
    $publicMessage = urlencode("BUENAS LOS PAQUETES DE " . $shipDate . " YA ESTÁN DISPONIBLES\n");
    $publicMessage .= urlencode("--------------------------------------------\n");
    
    foreach ($shipments as $shipment) {
        // Obtener los últimos 4 dígitos del número
        $last4 = substr($shipment['receiver_phone'], -4);
        $maskedPhone = "XXX" . $last4;
        
        $publicMessage .= urlencode("* " . $shipment['receiver_name'] . " Telf " . $maskedPhone . "\n");
    }
    
    $publicMessage .= urlencode("___________________________\n");
    $publicMessage .= urlencode("Esperen el mensaje WhatsApp de confirmación para venir a retirarlo.");

    // URLs de WhatsApp
    $adminWhatsappUrl = "https://wa.me/240222520265?text=" . $adminMessage;
    $groupWhatsappUrl = "https://wa.me/233552988797?text=" . $publicMessage;

    echo json_encode([
        'success' => true,
        'adminUrl' => $adminWhatsappUrl,
        'groupUrl' => $groupWhatsappUrl
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
