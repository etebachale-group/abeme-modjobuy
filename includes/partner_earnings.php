<?php
// Ensure DB is loaded regardless of caller's working directory
require_once __DIR__ . '/db.php';

function updatePartnerBenefits($partnerName) {
    global $pdo;
    
    try {
        // 1. Obtener el porcentaje del socio
        $stmt = $pdo->prepare("
            SELECT percentage 
            FROM partner_benefits 
            WHERE partner_name = ?
        ");
        $stmt->execute([$partnerName]);
        $percentage = $stmt->fetchColumn();

        // Permitir porcentaje 0.00 como válido; solo falla si no existe (false/null)
        if ($percentage === false || $percentage === null) {
            throw new Exception("No se encontró el porcentaje para el socio");
        }
        $percentage = (float)$percentage;

        // 2. Calcular beneficios base (envíos entregados × 2500)
        $stmt = $pdo->query("
            SELECT SUM(CASE 
                WHEN status = 'delivered' THEN weight * 2500 
                ELSE 0 
            END) as total_base_benefits
            FROM shipments
        ");
        $baseBenefits = $stmt->fetchColumn() ?: 0;

        // 3. Calcular beneficios adicionales totales (ingresos extra)
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(CASE 
                WHEN operation_type = 'add' THEN amount
                ELSE 0
            END), 0) as additional_income
            FROM expenses
        ");
        $additionalIncome = $stmt->fetchColumn();

        // 4. Calcular gastos totales (deducciones)
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(CASE 
                WHEN operation_type IN ('subtract', 'adjust') THEN amount
                ELSE 0
            END), 0) as total_expenses
            FROM expenses
        ");
        $totalExpenses = $stmt->fetchColumn();

        // 5. Calcular beneficio neto total
        $netBenefits = $baseBenefits + $additionalIncome - $totalExpenses;

        // 6. Calcular la parte correspondiente al socio según su porcentaje
        $totalBenefits = $netBenefits * ($percentage / 100);

        // 5. Obtener total de pagos confirmados
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) 
            FROM partner_payments 
            WHERE partner_name = ? 
            AND confirmed = 1
        ");
        $stmt->execute([$partnerName]);
        $totalPaidOut = $stmt->fetchColumn();

        // 3. Calcular balance actual
        $currentBalance = $totalBenefits - $totalPaidOut;

        // 4. Actualizar los totales en la tabla de beneficios
        $stmt = $pdo->prepare("
            UPDATE partner_benefits 
            SET total_earnings = ?,
                current_balance = ?,
                last_updated = CURRENT_TIMESTAMP
            WHERE partner_name = ?
        ");
        $stmt->execute([$totalBenefits, $currentBalance, $partnerName]);

        return [
            'success' => true,
            'total_earnings' => $totalBenefits,
            'current_balance' => $currentBalance
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
