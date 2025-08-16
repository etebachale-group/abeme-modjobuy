<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/partner_earnings.php';

// Ensure the user is authenticated
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['partner_name']) && isset($_POST['amount_paid'])) {
    $partnerName = $_POST['partner_name'];
    $amountPaid = floatval($_POST['amount_paid']);

    try {
        // Start a transaction for atomicity
        $pdo->beginTransaction();

        // 1. Actualizar los beneficios del socio para tener los valores más recientes
        $updateResult = updatePartnerBenefits($partnerName);
        if (!$updateResult['success']) {
            throw new Exception("Error al actualizar beneficios: " . $updateResult['error']);
        }

        // 2. Obtener el balance actual
        $stmt = $pdo->prepare("SELECT current_balance FROM partner_benefits WHERE partner_name = ?");
        $stmt->execute([$partnerName]);
        $currentBalance = $stmt->fetchColumn();

        if ($currentBalance === false) {
            throw new Exception("No se encontró al socio.");
        }

        if ($currentBalance < $amountPaid) {
            throw new Exception("El balance actual es insuficiente para este pago.");
        }

        // Calcular el nuevo balance
        $newBalance = $currentBalance - $amountPaid;

        // 3. Actualizar el balance del socio
        $stmt = $pdo->prepare("UPDATE partner_benefits SET current_balance = ? WHERE partner_name = ?");
        $stmt->execute([$newBalance, $partnerName]);

        // 3. Registrar el pago en la tabla partner_payments
        $stmt = $pdo->prepare("
            INSERT INTO partner_payments (
                partner_name, 
                amount, 
                payment_date,
                confirmation_date,
                confirmed,
                previous_balance,
                new_balance,
                notes
            ) VALUES (?, ?, NOW(), NOW(), TRUE, ?, ?, ?)
        ");
        $stmt->execute([
            $partnerName,
            $amountPaid,
            $currentBalance,
            $newBalance,
            'Pago confirmado automáticamente'
        ]);

        $pdo->commit(); // Commit the transaction

        echo json_encode(['success' => true, 'message' => 'Pago confirmado y balance actualizado.']);

    } catch (PDOException $e) {
        $pdo->rollBack(); // Rollback on PDO error
        error_log("PDOException in confirm_payment.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error de base de datos al confirmar el pago.']);
    } catch (Exception $e) {
        $pdo->rollBack(); // Rollback on general error
        error_log("Exception in confirm_payment.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos para confirmar el pago.']);
}
?>