<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/partner_earnings.php';

header('Content-Type: application/json');

try {
    $partner = $_GET['partner'] ?? '';
    if (!$partner) {
        throw new Exception('Socio no especificado');
    }

    // Actualizar los beneficios del socio
    $updateResult = updatePartnerBenefits($partner);
    if (!$updateResult['success']) {
        throw new Exception('Error al actualizar beneficios: ' . $updateResult['error']);
    }

    // Obtener los datos actualizados del socio
    $stmt = $pdo->prepare("
        SELECT 
            partner_name,
            percentage,
            role,
            join_date,
            total_earnings,
            current_balance,
            last_updated
        FROM partner_benefits 
        WHERE partner_name = ?
    ");

    $stmt->execute([$partner]);
    $partnerData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$partnerData) {
        throw new Exception('Socio no encontrado');
    }

    // Obtener el último pago
    $stmt = $pdo->prepare("
        SELECT 
            amount,
            payment_date,
            confirmation_date
        FROM partner_payments 
        WHERE partner_name = ? 
        AND confirmed = 1 
        ORDER BY payment_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$partner]);
    $lastPayment = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener historial de pagos
    $stmt = $pdo->prepare("
        SELECT 
            amount,
            payment_date,
            confirmation_date,
            confirmed,
            previous_balance,
            new_balance,
            notes
        FROM partner_payments 
        WHERE partner_name = ?
        ORDER BY payment_date DESC
    ");
    $stmt->execute([$partner]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener resumen de pagos
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN confirmed = 1 THEN amount ELSE 0 END) as total_confirmed,
            SUM(CASE WHEN confirmed = 0 THEN amount ELSE 0 END) as total_pending
        FROM partner_payments 
        WHERE partner_name = ?
    ");
    $stmt->execute([$partner]);
    $paymentSummary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Estructura de respuesta
    $response = [
        'name' => $partnerData['partner_name'],
        'role' => $partnerData['role'],
        'joinDate' => $partnerData['join_date'],
        'percentage' => $partnerData['percentage'],
        'totalEarnings' => $partnerData['total_earnings'],
        'currentBalance' => $partnerData['current_balance'],
        'lastPayment' => $lastPayment ? [
            'date' => $lastPayment['payment_date'],
            'amount' => $lastPayment['amount'],
            'confirmationDate' => $lastPayment['confirmation_date']
        ] : null,
        'lastUpdated' => $partnerData['last_updated'],
        'paymentHistory' => [
            'payments' => $payments,
            'summary' => [
                'totalPayments' => (int)$paymentSummary['total_payments'],
                'totalConfirmed' => (float)$paymentSummary['total_confirmed'],
                'totalPending' => (float)$paymentSummary['total_pending']
            ]
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener los datos del socio']);
    error_log("Error en partner_details.php: " . $e->getMessage());
}
