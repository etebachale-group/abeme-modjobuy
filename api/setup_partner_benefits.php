<?php
require_once '../includes/db.php';

try {
    // Leer el contenido del archivo SQL
    $sql = file_get_contents(__DIR__ . '/../sql/setup_partner_earnings.sql');
    
    // Dividir el archivo en declaraciones individuales
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    // Ejecutar cada declaración
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    // Verificar si hay datos existentes para migrar
    $stmt = $pdo->query("SELECT COUNT(*) FROM partner_benefits");
    $count = $stmt->fetchColumn();
    
    if ($count === 0) {
        // Insertar datos iniciales de socios si no existen
        $partners = [
            ['FERNANDO CHALE', 18.00, 'Principal'],
            ['MARIA CARMEN NSUE', 18.00, 'Principal'],
            ['GENEROSA ABEME', 30.00, 'Principal'],
            ['MARIA ISABEL', 8.00, 'Socio'],
            ['CAJA', 16.00, 'Caja'],
            ['FONDOS DE SOCIOS', 10.00, 'Fondos']
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO partner_benefits 
            (partner_name, percentage, role, join_date) 
            VALUES (?, ?, ?, '2023-01-01')
        ");
        
        foreach ($partners as $partner) {
            $stmt->execute($partner);
        }
    }
    
    // Actualizar los beneficios totales basados en envíos entregados
    $pdo->exec("
        UPDATE partner_benefits pb
        SET total_earnings = (
            SELECT COALESCE(SUM(weight * 2500 * (pb.percentage / 100)), 0)
            FROM shipments s
            WHERE s.status = 'delivered'
        ),
        current_balance = (
            SELECT COALESCE(SUM(weight * 2500 * (pb.percentage / 100)), 0)
            FROM shipments s
            WHERE s.status = 'delivered'
        ) - COALESCE(
            (SELECT SUM(amount)
             FROM partner_payments pp
             WHERE pp.partner_name = pb.partner_name
             AND pp.confirmed = 1),
            0
        )
    ");
    
    echo json_encode([
        'success' => true,
        'message' => 'Sistema de beneficios actualizado correctamente'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al configurar el sistema de beneficios: ' . $e->getMessage()
    ]);
}
