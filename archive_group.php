<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Verificar que sea una petición POST y el usuario esté autenticado
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isAuthenticated()) {
    http_response_code(403);
    exit('Acceso no autorizado');
}

// Obtener el group_code
$group_code = isset($_POST['group_code']) ? $_POST['group_code'] : '';

if (empty($group_code)) {
    http_response_code(400);
    exit('Código de grupo no proporcionado');
}

try {
    // Verificar si todos los paquetes están entregados
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total,
            SUM(CASE WHEN status = 'Entregado' THEN 1 ELSE 0 END) as delivered
        FROM shipments
        WHERE group_code = ?
    ");
    $stmt->execute([$group_code]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['total'] > 0 && $result['total'] == $result['delivered']) {
        // Archivar el grupo
        $stmt = $pdo->prepare("
            UPDATE shipment_groups
            SET is_archived = 1 
            WHERE group_code = ?
        ");
        $stmt->execute([$group_code]);

        echo json_encode(['success' => true, 'message' => 'Grupo archivado correctamente']);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'No se puede archivar el grupo: no todos los paquetes están entregados'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al archivar el grupo']);
}
?>
