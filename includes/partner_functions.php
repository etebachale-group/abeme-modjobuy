<?php
// Función para registrar un nuevo beneficio para un socio
function registerPartnerEarning($pdo, $partnerName, $amount, $source, $sourceId) {
    try {
        $stmt = $pdo->prepare("CALL register_partner_earning(?, ?, ?, ?)");
        return $stmt->execute([$partnerName, $amount, $source, $sourceId]);
    } catch (PDOException $e) {
        error_log("Error registrando beneficio: " . $e->getMessage());
        return false;
    }
}

// Función para confirmar un pago a un socio
function confirmPartnerPayment($pdo, $partnerName, $amount, $notes = '') {
    try {
        $pdo->beginTransaction();
        
        // Verificar balance actual
        $stmt = $pdo->prepare("
            SELECT current_balance 
            FROM partner_benefits 
            WHERE partner_name = ?
        ");
        $stmt->execute([$partnerName]);
        $currentBalance = $stmt->fetchColumn();
        
        if ($currentBalance < $amount) {
            throw new Exception("Balance insuficiente para realizar el pago");
        }
        
        // Registrar el pago
        $stmt = $pdo->prepare("
            INSERT INTO partner_payments 
            (partner_name, amount, confirmed, confirmation_date, previous_balance, new_balance, notes)
            VALUES (?, ?, 1, NOW(), ?, ?, ?)
        ");
        
        $newBalance = $currentBalance - $amount;
        $stmt->execute([$partnerName, $amount, $currentBalance, $newBalance, $notes]);
        
        // Actualizar el balance actual
        $stmt = $pdo->prepare("
            UPDATE partner_benefits
            SET current_balance = ?
            WHERE partner_name = ?
        ");
        $stmt->execute([$newBalance, $partnerName]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error confirmando pago: " . $e->getMessage());
        return false;
    }
}

// Función para obtener el historial de beneficios de un socio
function getPartnerEarningHistory($pdo, $partnerName) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                peh.*,
                CASE 
                    WHEN peh.source = 'shipment' THEN s.code
                    WHEN peh.source = 'expense' THEN e.description
                    ELSE NULL
                END as source_reference
            FROM partner_earnings_history peh
            LEFT JOIN shipments s ON peh.source = 'shipment' AND peh.source_id = s.id
            LEFT JOIN expenses e ON peh.source = 'expense' AND peh.source_id = e.id
            WHERE peh.partner_name = ?
            ORDER BY peh.earned_date DESC
        ");
        $stmt->execute([$partnerName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error obteniendo historial: " . $e->getMessage());
        return [];
    }
}

// Función para obtener el historial de pagos de un socio
function getPartnerPaymentHistory($pdo, $partnerName) {
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM partner_payments
            WHERE partner_name = ?
            ORDER BY payment_date DESC
        ");
        $stmt->execute([$partnerName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error obteniendo historial de pagos: " . $e->getMessage());
        return [];
    }
}
