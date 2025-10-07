<?php
require_once 'includes/db.php';

try {
    // Limpiar datos existentes
    $pdo->exec("DELETE FROM shipments WHERE code LIKE 'TEST%'");
    $pdo->exec("DELETE FROM expenses WHERE description LIKE 'TEST%'");
    $pdo->exec("UPDATE partner_benefits SET total_benefits = 0, total_expenses = 0, current_balance = 0");
    $pdo->exec("UPDATE system_metrics SET metric_value = 0 WHERE metric_name = 'total_accumulated_benefits'");

    // Insertar envíos
    $query = "INSERT INTO shipments (code, group_code, sender_name, sender_phone, receiver_name, receiver_phone, product, weight, shipping_cost, sale_price, advance_payment, profit, ship_date, est_date, status) VALUES
        ('TEST-001', 'agosto-14-25', 'Juan Pérez', '123456789', 'María García', '987654321', 'Ropa', 15.5, 15000, 100750, 50000, 38750, '2025-08-01', '2025-08-15', 'delivered'),
        ('TEST-002', 'agosto-14-25', 'Ana Martínez', '456789123', 'Pedro Sánchez', '321654987', 'Electrónicos', 8.2, 8000, 53300, 30000, 20500, '2025-08-02', '2025-08-16', 'delivered'),
        ('TEST-003', 'agosto-14-25', 'Carlos López', '789123456', 'Laura Torres', '654987321', 'Alimentos', 25.0, 25000, 162500, 80000, 62500, '2025-08-03', '2025-08-17', 'delivered')";
    $pdo->exec($query);
    echo "Datos de prueba insertados correctamente.\n";
} catch (PDOException $e) {
    die('Error en la base de datos: ' . $e->getMessage());
}

// Insertar gastos
$query = "INSERT INTO expenses (description, amount, paid_by, date, operation_type) VALUES
    ('TEST - Combustible', 50000, 'FERNANDO CHALE', '2025-08-05', 'subtract'),
    ('TEST - Mantenimiento', 75000, 'GENEROSA ABEME', '2025-08-08', 'subtract'),
    ('TEST - Suministros', 30000, 'MARIA CARMEN NSUE', '2025-08-10', 'subtract')";
$conn->query($query) or die($conn->error);

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
    $conn->query("INSERT IGNORE INTO partner_benefits (partner_name) VALUES ('" . $conn->real_escape_string($partner) . "')");
}

// Actualizar grupos
$conn->query("
    INSERT IGNORE INTO shipment_groups (group_code, is_archived)
    SELECT DISTINCT group_code, 1
    FROM shipments
    WHERE group_code IS NOT NULL
    AND status = 'delivered'
") or die($conn->error);

echo "Datos de prueba insertados correctamente.";
?>
