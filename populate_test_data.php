<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Limpiar datos existentes
$pdo->exec("DELETE FROM shipments WHERE code LIKE 'TEST%'");
$pdo->exec("DELETE FROM expenses WHERE description LIKE 'TEST%'");
$pdo->exec("UPDATE partner_benefits SET total_benefits = 0, total_expenses = 0, current_balance = 0");
$pdo->exec("UPDATE system_metrics SET metric_value = 0 WHERE metric_name = 'total_accumulated_benefits'");

// Datos de ejemplo para envíos
$shipments = [
    [
        'code' => 'TEST-001',
        'group_code' => 'agosto-14-25',
        'sender_name' => 'Juan Pérez',
        'sender_phone' => '123456789',
        'receiver_name' => 'María García',
        'receiver_phone' => '987654321',
        'product' => 'Ropa',
        'weight' => 15.5,
        'shipping_cost' => 15000,
        'sale_price' => 100750,
        'advance_payment' => 50000,
        'profit' => 38750,
        'ship_date' => '2025-08-01',
        'est_date' => '2025-08-15',
        'status' => 'delivered'
    ],
    [
        'code' => 'TEST-002',
        'group_code' => 'agosto-14-25',
        'sender_name' => 'Ana Martínez',
        'sender_phone' => '456789123',
        'receiver_name' => 'Pedro Sánchez',
        'receiver_phone' => '321654987',
        'product' => 'Electrónicos',
        'weight' => 8.2,
        'shipping_cost' => 8000,
        'sale_price' => 53300,
        'advance_payment' => 30000,
        'profit' => 20500,
        'ship_date' => '2025-08-02',
        'est_date' => '2025-08-16',
        'status' => 'delivered'
    ],
    [
        'code' => 'TEST-003',
        'group_code' => 'agosto-14-25',
        'sender_name' => 'Carlos López',
        'sender_phone' => '789123456',
        'receiver_name' => 'Laura Torres',
        'receiver_phone' => '654987321',
        'product' => 'Alimentos',
        'weight' => 25.0,
        'shipping_cost' => 25000,
        'sale_price' => 162500,
        'advance_payment' => 80000,
        'profit' => 62500,
        'ship_date' => '2025-08-03',
        'est_date' => '2025-08-17',
        'status' => 'delivered'
    ]
];

// Insertar envíos
foreach ($shipments as $shipment) {
    try {
        createShipment($pdo, $shipment);
    } catch (Exception $e) {
        echo "Error al crear envío {$shipment['code']}: " . $e->getMessage() . "\n";
    }
}

// Insertar algunos gastos de ejemplo
$expenses = [
    [
        'description' => 'TEST - Combustible',
        'amount' => 50000,
        'paid_by' => 'FERNANDO CHALE',
        'date' => '2025-08-05',
        'operation_type' => 'subtract'
    ],
    [
        'description' => 'TEST - Mantenimiento',
        'amount' => 75000,
        'paid_by' => 'GENEROSA ABEME',
        'date' => '2025-08-08',
        'operation_type' => 'subtract'
    ],
    [
        'description' => 'TEST - Suministros',
        'amount' => 30000,
        'paid_by' => 'MARIA CARMEN NSUE',
        'date' => '2025-08-10',
        'operation_type' => 'subtract'
    ]
];

// Insertar gastos
foreach ($expenses as $expense) {
    try {
        addExpense(
            $pdo,
            $expense['description'],
            $expense['amount'],
            $expense['paid_by'],
            $expense['date'],
            $expense['operation_type']
        );
    } catch (Exception $e) {
        echo "Error al crear gasto {$expense['description']}: " . $e->getMessage() . "\n";
    }
}

// Asegurarse de que los grupos se actualicen correctamente
$pdo->query("
    INSERT IGNORE INTO shipment_groups (group_code, is_archived)
    SELECT DISTINCT group_code, 1
    FROM shipments
    WHERE group_code IS NOT NULL
    AND status = 'delivered'
");

echo "Datos de prueba insertados correctamente.\n";
echo "Total de envíos: " . count($shipments) . "\n";
echo "Total de gastos: " . count($expenses) . "\n";
?>
