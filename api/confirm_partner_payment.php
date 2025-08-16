<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Verificar que el usuario esté autenticado
requireAuth();

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);
$partnerName = $data['partnerName'] ?? '';
$amount = $data['amount'] ?? 0;
$notes = $data['notes'] ?? '';

if (!$partnerName || !$amount) {
    echo json_encode(['error' => 'Faltan datos requeridos']);
    exit;
}

try {
    // Iniciar transacción
    $pdo->beginTransaction();

    // Verificar que el socio existe y tiene suficiente balance
    $stmt = $pdo->prepare("
        SELECT current_balance 
        FROM partner_benefits 
        WHERE partner_name = ? 
        AND current_balance >= ?
    ");
    $stmt->execute([$partnerName, $amount]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Balance insuficiente o socio no encontrado');
    }

    // Registrar el pago
    $stmt = $pdo->prepare("
        INSERT INTO partner_payments 
        (partner_name, amount, confirmed, confirmation_date, notes) 
        VALUES (?, ?, 1, NOW(), ?)
    ");
    $stmt->execute([$partnerName, $amount, $notes]);

    // El trigger se encargará de actualizar el balance actual del socio

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Pago confirmado exitosamente'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
