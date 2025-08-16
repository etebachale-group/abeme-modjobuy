<?php
require_once 'includes/db.php';
try {
    // Limpiar datos existentes
    $pdo->exec("DELETE FROM shipments WHERE code LIKE 'TEST%'");
    $pdo->exec("DELETE FROM expenses WHERE description LIKE 'TEST%'");
    $pdo->exec("UPDATE partner_benefits SET total_benefits = 0, total_expenses = 0, current_balance = 0");
    $pdo->exec("UPDATE system_metrics SET metric_value = 0 WHERE metric_name = 'total_accumulated_benefits'");

    // Insertar envíos
    $stmt = $pdo->prepare("INSERT INTO shipments (code, group_code, sender_name, sender_phone, receiver_name, receiver_phone, product, weight, shipping_cost, sale_price, advance_payment, profit, ship_date, est_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $shipments = [
        ['TEST-001', 'agosto-14-25', 'Juan Pérez', '123456789', 'María García', '987654321', 'Ropa', 15.5, 15000, 100750, 50000, 38750, '2025-08-01', '2025-08-15', 'delivered'],
        ['TEST-002', 'agosto-14-25', 'Ana Martínez', '456789123', 'Pedro Sánchez', '321654987', 'Electrónicos', 8.2, 8000, 53300, 30000, 20500, '2025-08-02', '2025-08-16', 'delivered'],
        ['TEST-003', 'agosto-14-25', 'Carlos López', '789123456', 'Laura Torres', '654987321', 'Alimentos', 25.0, 25000, 162500, 80000, 62500, '2025-08-03', '2025-08-17', 'delivered']
    ];

    foreach ($shipments as $shipment) {
        $stmt->execute($shipment);
    }

    // Insertar gastos
    $stmt = $pdo->prepare("INSERT INTO expenses (description, amount, paid_by, date, operation_type) VALUES (?, ?, ?, ?, ?)");

    $expenses = [
        ['TEST - Combustible', 50000, 'FERNANDO CHALE', '2025-08-05', 'subtract'],
        ['TEST - Mantenimiento', 75000, 'GENEROSA ABEME', '2025-08-08', 'subtract'],
        ['TEST - Suministros', 30000, 'MARIA CARMEN NSUE', '2025-08-10', 'subtract']
    ];

    foreach ($expenses as $expense) {
        $stmt->execute($expense);
    }

    // Inicializar tabla de beneficios de socios si no existe
    $partners = [
        'FERNANDO CHALE',
        'MARIA CARMEN NSUE',
        'GENEROSA ABEME',
        'MARIA ISABEL',
        'CAJA',
        'FONDOS DE SOCIOS'
    ];

    foreach ($partners as $partner) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO partner_benefits (partner_name) VALUES (?)");
        $stmt->execute([$partner]);
    }

    // Actualizar grupos de envíos
    $pdo->exec("INSERT IGNORE INTO shipment_groups (group_code, is_archived) 
                SELECT DISTINCT group_code, 1 
                FROM shipments 
                WHERE group_code IS NOT NULL 
                AND status = 'delivered'");

    // Actualizar beneficios
    foreach ($shipments as $shipment) {
        $benefit = $shipment[7] * 2500; // weight * 2500
        $stmt = $pdo->prepare("UPDATE partner_benefits SET total_benefits = total_benefits + ?, current_balance = current_balance + ? WHERE partner_name = ?");
        
        // FERNANDO CHALE (18%)
        $amount = $benefit * 0.18;
        $stmt->execute([$amount, $amount, 'FERNANDO CHALE']);
        
        // MARIA CARMEN NSUE (18%)
        $stmt->execute([$amount, $amount, 'MARIA CARMEN NSUE']);
        
        // GENEROSA ABEME (30%)
        $amount = $benefit * 0.30;
        $stmt->execute([$amount, $amount, 'GENEROSA ABEME']);
        
        // MARIA ISABEL (8%)
        $amount = $benefit * 0.08;
        $stmt->execute([$amount, $amount, 'MARIA ISABEL']);
        
        // CAJA (16%)
        $amount = $benefit * 0.16;
        $stmt->execute([$amount, $amount, 'CAJA']);
        
        // FONDOS DE SOCIOS (10%)
        $amount = $benefit * 0.10;
        $stmt->execute([$amount, $amount, 'FONDOS DE SOCIOS']);

        // Actualizar métricas del sistema
        $pdo->exec("UPDATE system_metrics SET metric_value = metric_value + $benefit WHERE metric_name = 'total_accumulated_benefits'");
    }

    echo "Datos de prueba insertados correctamente.";
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
