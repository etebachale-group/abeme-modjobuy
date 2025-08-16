<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Solo usuarios autenticados pueden ver esta página
requireAuth();

// Obtener los grupos archivados
function getArchivedGroups($pdo) {
    $stmt = $pdo->query("
        SELECT 
            s.group_code,
            DATE_FORMAT(s.ship_date, '%d/%m/%Y') as formatted_date,
            COUNT(*) as total_shipments,
            GROUP_CONCAT(s.code) as shipment_codes,
            MIN(s.ship_date) as group_date
        FROM shipments s
        INNER JOIN shipment_groups sg ON s.group_code = sg.group_code
        WHERE sg.is_archived = 1
        GROUP BY s.group_code, s.ship_date
        HAVING COUNT(*) = SUM(CASE WHEN s.status = 'delivered' THEN 1 ELSE 0 END)
        ORDER BY group_date DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener los envíos de un grupo específico
function getArchivedShipmentsByGroup($pdo, $groupCode) {
    $stmt = $pdo->prepare("
        SELECT s.* 
        FROM shipments s
        INNER JOIN shipment_groups sg ON s.group_code = sg.group_code
        WHERE s.group_code = ? 
        AND sg.is_archived = 1 
        AND s.status = 'delivered'
        ORDER BY s.ship_date DESC
    ");
    $stmt->execute([$groupCode]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$archivedGroups = getArchivedGroups($pdo);

// Incluir el encabezado
include 'includes/header.php';
?>

<div class="container">
    <h1 class="page-title">Envíos Archivados</h1>
    
    <div class="archived-groups">
        <?php foreach ($archivedGroups as $group): ?>
            <div class="shipment-group">
                <div class="group-header" id="group-<?php echo htmlspecialchars($group['group_code']); ?>">
                    <h2>
                        <span class="group-toggle" id="group-<?php echo htmlspecialchars($group['group_code']); ?>-toggle">
                            <i class="fas fa-chevron-down"></i>
                        </span>
                        Grupo: <?php echo htmlspecialchars($group['group_code']); ?>
                        <span class="group-date"><?php echo htmlspecialchars($group['formatted_date']); ?></span>
                        <span class="total-shipments">Total: <?php echo $group['total_shipments']; ?> envíos</span>
                    </h2>
                </div>
                
                <div class="group-content hidden" id="group-<?php echo htmlspecialchars($group['group_code']); ?>-content">
                    <div class="shipments-grid">
                        <?php 
                        $shipments = getArchivedShipmentsByGroup($pdo, $group['group_code']);
                        foreach ($shipments as $shipment):
                        ?>
                        <div class="shipment-card">
                            <div class="shipment-header">
                                <span class="shipment-code"><?php echo htmlspecialchars($shipment['code']); ?></span>
                                <span class="status-indicator <?php echo htmlspecialchars(strtolower($shipment['status'])); ?>">
                                    <?php echo htmlspecialchars($shipment['status']); ?>
                                </span>
                            </div>
                            <div class="shipment-details">
                                <div class="detail-item">
                                    <label>Remitente:</label>
                                    <span><?php echo htmlspecialchars($shipment['sender_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <label>Destinatario:</label>
                                    <span><?php echo htmlspecialchars($shipment['receiver_name']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <label>Producto:</label>
                                    <span><?php echo htmlspecialchars($shipment['product']); ?></span>
                                </div>
                                <div class="detail-item">
                                    <label>Peso:</label>
                                    <span><?php echo htmlspecialchars($shipment['weight']); ?> kg</span>
                                </div>
                                <div class="detail-item">
                                    <label>Precio:</label>
                                    <span><?php echo number_format($shipment['sale_price'], 0, ',', '.'); ?> XAF</span>
                                </div>
                                <div class="detail-item">
                                    <label>Adelanto:</label>
                                    <span><?php echo number_format($shipment['advance_payment'], 2, ',', '.'); ?> XAF</span>
                                </div>
                            </div>
                            <div class="shipment-footer">
                                <span class="ship-date">Enviado: <?php echo htmlspecialchars($shipment['ship_date']); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Función para mostrar/ocultar los envíos de un grupo
    function toggleGroupShipments(groupCode) {
        const groupContent = document.querySelector(`#group-${groupCode}-content`);
        const groupHeader = document.querySelector(`#group-${groupCode}`);
        if (groupContent) {
            groupContent.classList.toggle('hidden');
            groupHeader.classList.toggle('active');
            const toggleIcon = document.querySelector(`#group-${groupCode}-toggle i`);
            if (toggleIcon) {
                toggleIcon.classList.toggle('fa-chevron-down');
                toggleIcon.classList.toggle('fa-chevron-up');
            }
            
            // Si el contenido está visible, hacemos scroll al grupo
            if (!groupContent.classList.contains('hidden')) {
                groupHeader.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    }

    // Agregar eventos click a los encabezados de grupo
    document.querySelectorAll('.group-header').forEach(header => {
        header.addEventListener('click', function() {
            const groupCode = this.id.replace('group-', '');
            toggleGroupShipments(groupCode);
        });
    });

    // Auto expandir si hay un solo grupo
    const groups = document.querySelectorAll('.shipment-group');
    if (groups.length === 1) {
        const groupCode = groups[0].querySelector('.group-header').id.replace('group-', '');
        toggleGroupShipments(groupCode);
    }

    // Asegurarse de que el scroll horizontal funcione bien en dispositivos táctiles
    document.querySelectorAll('.shipments-grid').forEach(grid => {
        let isDown = false;
        let startX;
        let scrollLeft;

        grid.addEventListener('mousedown', (e) => {
            isDown = true;
            grid.classList.add('active');
            startX = e.pageX - grid.offsetLeft;
            scrollLeft = grid.scrollLeft;
        });

        grid.addEventListener('mouseleave', () => {
            isDown = false;
            grid.classList.remove('active');
        });

        grid.addEventListener('mouseup', () => {
            isDown = false;
            grid.classList.remove('active');
        });

        grid.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - grid.offsetLeft;
            const walk = (x - startX) * 2;
            grid.scrollLeft = scrollLeft - walk;
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
