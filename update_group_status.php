<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Verificar autenticación
if (!isAuthenticated()) {
    http_response_code(403);
    exit(json_encode(['error' => 'No autorizado']));
}

try {
    // Obtener grupos donde todos los paquetes están entregados pero el grupo no está archivado
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.group_code, 
               COUNT(*) as total_packages,
               SUM(CASE WHEN s.status = 'Entregado' THEN 1 ELSE 0 END) as delivered_packages
        FROM shipments s
        LEFT JOIN shipment_groups sg ON s.group_code = sg.group_code
        WHERE (sg.is_archived = 0 OR sg.is_archived IS NULL)
        GROUP BY s.group_code
        HAVING total_packages = delivered_packages
    ");
    $stmt->execute();
    
    $completedGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Actualizar el estado de los grupos completados
    foreach ($completedGroups as $group) {
        $updateStmt = $pdo->prepare("
            UPDATE shipment_groups 
            SET is_archived = 1 
            WHERE group_code = ?
        ");
        $updateStmt->execute([$group['group_code']]);
    }
    
    echo json_encode([
        'success' => true,
        'completedGroups' => $completedGroups
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al verificar el estado de los grupos'
    ]);
}
?>