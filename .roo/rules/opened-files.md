# Opened Files
## File Name
includes\db.php
## File Content
<?php
$host = 'localhost';
$dbname = 'abeme_modjobuy';
$username = 'root';
$password = '';

try {
    // First try to connect using PDO
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If PDO fails, try mysqli
    $mysqli = new mysqli($host, $username, $password, $dbname);
    
    if ($mysqli->connect_error) {
        die("Error de conexión a la base de datos: " . $mysqli->connect_error);
    }
    
    $mysqli->set_charset("utf8");
    
    // Create a PDO-like wrapper for mysqli
    class PDOWrapper {
        private $mysqli;
        
        public function __construct($mysqli) {
            $this->mysqli = $mysqli;
        }
        
        public function prepare($query) {
            return new PDOStatementWrapper($this->mysqli, $query);
        }
        
        public function query($query) {
            $result = $this->mysqli->query($query);
            if ($result === false) {
                throw new Exception($this->mysqli->error);
            }
            return new PDOStatementWrapper($this->mysqli, $query, $result);
        }
        
        public function exec($query) {
            return $this->mysqli->query($query);
        }
        
        public function lastInsertId() {
            return $this->mysqli->insert_id;
        }
    }
    
    class PDOStatementWrapper {
        private $mysqli;
        private $query;
        private $result;
        private $params = [];
        
        public function __construct($mysqli, $query, $result = null) {
            $this->mysqli = $mysqli;
            $this->query = $query;
            $this->result = $result;
        }
        
        public function execute($params = null) {
            if ($params) {
                $this->params = $params;
            }
            
            $query = $this->query;
            if (!empty($this->params)) {
                foreach ($this->params as $param) {
                    $param = $this->mysqli->real_escape_string($param);
                    $query = preg_replace('/\?/', "'$param'", $query, 1);
                }
            }
            
            $this->result = $this->mysqli->query($query);
            return $this->result !== false;
        }
        
        public function fetch($fetch_style = null) {
            if (!$this->result) return false;
            return $this->result->fetch_assoc();
        }
        
        public function fetchAll($fetch_style = null) {
            if (!$this->result) return [];
            $rows = [];
            while ($row = $this->result->fetch_assoc()) {
                $rows[] = $row;
            }
            return $rows;
        }
        
        public function fetchColumn() {
            if (!$this->result) return false;
            $row = $this->result->fetch_row();
            return $row ? $row[0] : null;
        }
    }
    
    $pdo = new PDOWrapper($mysqli);
}

// Cargar funciones si no están cargadas
if (!function_exists('createAdminIfNotExists')) {
    require_once 'functions.php';
}

// Crear usuario admin si no existe
createAdminIfNotExists($pdo);
?>
# Opened Files
## File Name
archived_shipments.php
## File Content
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

# Opened Files
## File Name
css\benefits-mobile.css
## File Content
/* Mobile-first responsive design for benefits page */
:root {
    --primary-color: #2196F3;
    --secondary-color: #1976D2;
    --success-color: #4CAF50;
    --danger-color: #f44336;
    --warning-color: #FF9800;
    --info-color: #00BCD4;
    --light-color: #f8f9fa;
    --dark-color: #343a40;
    --gray-color: #6c757d;
    --border-color: #dee2e6;
    --header-height: 60px;
    --footer-height: 60px;
}

/* Base mobile styles */
body {
    font-family: 'Roboto', 'Segoe UI', Arial, sans-serif;
    background-color: #f5f7fa;
    color: #333;
    margin: 0;
    padding: 0;
    padding-top: var(--header-height);
    padding-bottom: var(--footer-height);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* Header styles */
.header {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: var(--header-height);
    z-index: 1000;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.nav-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 100%;
    padding: 0 1rem;
}

.logo-img {
    height: 40px;
    width: auto;
}

.header-title h1 {
    font-size: 1.2rem;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.header-title p {
    font-size: 0.7rem;
    opacity: 0.9;
    margin: 0;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.4rem 0.8rem;
    border: none;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
    text-align: center;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s;
    gap: 0.3rem;
    min-height: 36px;
}

.btn i {
    font-size: 0.9rem;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
    font-size: 0.7rem;
    padding: 0.3rem 0.6rem;
}

.btn-secondary:hover {
    background-color: #5a6268;
}

/* Main content */
.main-content {
    padding: 1rem;
    padding-top: calc(var(--header-height) + 1rem);
    padding-bottom: calc(var(--footer-height) + 1rem);
}

.dashboard-section {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Stats summary */
.stats-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 0 -0.25rem;
    width: calc(100% + 0.5rem);
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 0.8rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
    flex: 1 1 calc(33.333% - 0.5rem);
    min-width: calc(33.333% - 0.5rem);
    max-width: calc(33.333% - 0.5rem);
    box-sizing: border-box;
    margin: 0;
}

.stat-icon {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    color: var(--primary-color);
}

.stat-info h3 {
    margin: 0 0 0.3rem 0;
    font-size: 0.9rem;
    color: var(--dark-color);
}

.stat-value {
    font-size: 1.2rem;
    font-weight: 700;
    margin: 0;
    color: var(--primary-color);
}

/* Section titles */
.benefits-distribution h2,
.detailed-view h2 {
    color: var(--dark-color);
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid var(--primary-color);
    font-size: 1.2rem;
}

/* Distribution chart */
.distribution-chart {
    margin: 1rem 0;
    height: 200px;
    display: flex;
    justify-content: center;
    align-items: center;
}

/* Partners grid */
.partners-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    margin: 1rem 0;
}

.partner-card {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    border: 1px solid var(--border-color);
}

.partner-card.main-partner {
    border-top: 3px solid var(--warning-color);
    background: #fff8e1;
}

.partner-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.8rem;
}

.partner-header h3 {
    margin: 0;
    font-size: 1rem;
    color: var(--dark-color);
}

.percentage {
    background: var(--primary-color);
    color: white;
    padding: 0.1rem 0.4rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.partner-amount p {
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0 0 0.8rem 0;
    color: var(--success-color);
}

.progress-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--primary-color);
    border-radius: 4px;
    transition: width 0.5s ease;
}

/* Benefits table */
.benefits-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    @media (max-width: 600px) {
        .main-content, .nav-container {
            max-width: 100vw;
            padding: 0.5rem;
            margin: 0;
        }
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            width: 100vw;
            margin: 0;
        }
        .stat-card {
            min-width: 0;
            max-width: 100%;
            padding: 0.7rem 0.2rem;
            box-sizing: border-box;
        }
        .partner-card {
            min-width: 120px;
            max-width: 100vw;
            padding: 0.7rem 0.3rem;
        }
        .modal-content {
            margin: 5vh auto;
            padding: 0.7rem;
        }
        .detail-grid {
            grid-template-columns: 1fr;
        }
    }
.benefits-table tr:last-child td {
    border-bottom: none;
}

.benefits-table .main-partner-row {
    background: #fff8e1;
}

.partner-info {
    display: flex;
    flex-direction: column;
}

.partner-name {
    font-weight: 500;
    font-size: 0.9rem;
}

.main-partner-badge {
    background: var(--warning-color);
    color: white;
    padding: 0.1rem 0.3rem;
    border-radius: 3px;
    font-size: 0.6rem;
    margin-top: 0.2rem;
    align-self: flex-start;
    display: inline-block;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    overflow-y: auto;
}

.modal-content {
    background-color: #fefefe;
    margin: 1rem auto;
    padding: 1rem;
    border: none;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    position: relative;
}

.close {
    color: #aaa;
    float: right;
    font-size: 24px;
    font-weight: bold;
    position: absolute;
    right: 0.5rem;
    top: 0.5rem;
    cursor: pointer;
    padding: 0.5rem;
}

.close:hover,
.close:focus {
    color: #000;
    text-decoration: none;
    cursor: pointer;
}

.partner-details h3 {
    margin-top: 0;
    font-size: 1.2rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.5rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    padding: 0.3rem 0;
    border-bottom: 1px solid #eee;
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 500;
    color: #555;
}

.detail-value {
    text-align: right;
}

/* Notification styles */
.notification {
    position: fixed;
    top: calc(var(--header-height) + 1rem);
    left: 1rem;
    right: 1rem;
    padding: 1rem;
    border-radius: 4px;
    color: white;
    font-weight: 500;
    z-index: 1000;
    animation: slideIn 0.3s ease-out;
    text-align: center;
}

.notification.success {
    background: var(--success-color);
}

.notification.error {
    background: var(--danger-color);
}

@keyframes slideIn {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* Tablet styles */
@media (min-width: 768px) {
    .stats-summary {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .partners-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .benefits-table th,
    .benefits-table td {
        padding: 1rem;
    }
    
    .header-title h1 {
        font-size: 1.5rem;
    }
    
    .header-title p {
        font-size: 0.9rem;
    }
    
    .btn {
        font-size: 0.9rem;
        padding: 0.5rem 1rem;
    }
    
    .btn i {
        font-size: 1rem;
    }
    
    .distribution-chart {
        height: 300px;
    }
}

/* Desktop styles */
@media (min-width: 1024px) {
    .main-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem;
    }
    
    .dashboard-section {
        padding: 2rem;
    }
    
    .stats-summary {
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
    }
    
    .stat-card {
        padding: 1.5rem;
    }
    
    .stat-icon {
        font-size: 2rem;
    }
    
    .stat-info h3 {
        font-size: 1.1rem;
    }
    
    .stat-value {
        font-size: 1.5rem;
    }
    
    .partners-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
    }
    
    .partner-card {
        padding: 1.5rem;
    }
    
    .partner-header h3 {
        font-size: 1.2rem;
    }
    
    .partner-amount p {
        font-size: 1.3rem;
    }
    
    .progress-bar {
        height: 10px;
    }
    
    .benefits-table {
        font-size: 1rem;
    }
    
    .benefits-table th,
    .benefits-table td {
        padding: 1rem;
    }
    
    .modal-content {
        padding: 2rem;
        max-width: 600px;
    }
    
    .partner-details h3 {
        font-size: 1.5rem;
    }
    
    .detail-grid {
        grid-template-columns: 1fr 1fr;
    }
}

/* Large screen styles */
@media (min-width: 1200px) {
    .partners-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Touch target optimization */
.btn,
.nav-link,
.partner-card,
.stat-card {
    -webkit-tap-highlight-color: transparent;
    -webkit-touch-callout: none;
    -webkit-user-select: none;
    user-select: none;
}

/* Scrollbar styling for mobile */
::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 3px;
}

/* Focus states for accessibility */
.btn:focus,
.nav-link:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

/* Animation for loading states */
.loading {
    opacity: 0.7;
    pointer-events: none;
}

/* Responsive images */
img {
    max-width: 100%;
    height: auto;
}

/* Prevent text selection on mobile */
body {
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
}

/* Enable text selection for content areas */
.content,
.stat-value,
.partner-amount,
.detail-value {
    -webkit-user-select: text;
    -moz-user-select: text;
    -ms-user-select: text;
    user-select: text;
}
# Opened Files
## File Name
js\benefits.js
## File Content
// Función para inicializar el gráfico de beneficios
document.addEventListener('DOMContentLoaded', function() {
    // Datos de los socios y sus porcentajes
    const partners = {
        'FERNANDO CHALE': 18,
        'MARIA CARMEN NSUE': 18,
        'GENEROSA ABEME': 30,
        'MARIA ISABEL': 8,
        'CAJA': 16,
        'FONDOS DE SOCIOS': 10
    };

    // Inicializar el gráfico de beneficios
    initBenefitsChart(partners);

    // Configurar el modal
    const modal = document.getElementById('detailsModal');
    const closeBtn = document.querySelector('.close');
    
    if (closeBtn) {
        closeBtn.onclick = closeModal;
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }
    
    // Cerrar modal con tecla Escape
    window.onkeydown = function(event) {
        if (event.key === 'Escape' && modal.classList.contains('show')) {
            closeModal();
        }
    }

    // Función para cerrar el modal con animación
    function closeModal() {
        const modal = document.getElementById('detailsModal');
        if (!modal) return;
        
        modal.classList.remove('show');
        setTimeout(() => {
            modal.style.display = 'none';
        }, 200); // Esperar a que termine la animación
    }
    
    // Prevenir el cierre del modal al hacer clic dentro
    const modalContent = document.querySelector('.modal-content');
    if (modalContent) {
        modalContent.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    }
});

// Función para inicializar el gráfico de beneficios
function initBenefitsChart(partners) {
    const ctx = document.getElementById('benefitsChart').getContext('2d');
    const partnerColors = {
        'FERNANDO CHALE': '#1976d2',      // Azul principal
        'MARIA CARMEN NSUE': '#2196f3',   // Azul secundario
        'GENEROSA ABEME': '#0d47a1',      // Azul oscuro
        'MARIA ISABEL': '#64b5f6',        // Azul claro
        'CAJA': '#90caf9',                // Azul más claro
        'FONDOS DE SOCIOS': '#bbdefb'     // Azul muy claro
    };

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(partners),
            datasets: [{
                data: Object.values(partners),
                backgroundColor: Object.keys(partners).map(partner => partnerColors[partner]),
                borderColor: 'white',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        font: {
                            family: 'Roboto',
                            size: 12
                        },
                        color: '#333333',
                        padding: 15,
                        boxWidth: 12
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            return `${label}: ${value}%`;
                        }
                    }
                }
            },
            cutout: '60%',
            animation: {
                animateRotate: true,
                animateScale: true
            }
        }
    });
}

// Función para mostrar detalles del socio
function viewDetails(partnerName) {
    const modal = document.getElementById('detailsModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    if (!modal || !modalTitle || !modalBody) return;

    modalTitle.textContent = `Detalles de ${partnerName}`;
    modalBody.innerHTML = `
        <div class="partner-details">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Cargando datos del socio...</span>
            </div>
        </div>`;

    // Mostrar el modal con animación
    modal.style.display = 'block';
    // Forzar un reflow
    modal.offsetHeight;
    modal.classList.add('show');

    // Primero verificamos y actualizamos la estructura de la tabla
    fetch('api/update_table_structure.php')
        .then(response => response.json())
        .then(() => {
            // Luego verificamos las tablas completas
            return fetch('api/check_partner_tables.php');
        })
        .then(response => response.json())
        .then(() => {
            // Finalmente obtenemos los detalles del socio
            return fetch(`api/partner_details.php?partner=${encodeURIComponent(partnerName)}`);
        })
        .then(response => response.json())
        .then(details => {
            if (details.error) {
                modalBody.innerHTML = `
                    <div class="partner-details">
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>${details.error}</span>
                        </div>
                    </div>`;
                return;
            }

            const formattedDate = details.joinDate ? new Date(details.joinDate).toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }) : 'No especificado';

            const lastPaymentDate = details.lastPayment ? new Date(details.lastPayment).toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            }) : 'No especificado';

            modalBody.innerHTML = `
                <div class="partner-details">
                    <div class="partner-header">
                        <h3>${partnerName}</h3>
                    </div>
                    <div class="partner-summary">
                        <div class="summary-card">
                            <h4>Ganancias Totales</h4>
                            <p>XAF ${Number(details.totalEarnings).toLocaleString('es-ES')}</p>
                        </div>
                        <div class="summary-card">
                            <h4>Balance Actual</h4>
                            <p>XAF ${Number(details.currentBalance).toLocaleString('es-ES')}</p>
                        </div>
                    </div>
                </div>

                <div class="payment-history">
                    <h4>Historial de Pagos</h4>
                    <div class="table-responsive">
                        <table id="paymentsHistory" class="table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th>Balance Anterior</th>
                                    <th>Nuevo Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${details.paymentHistory.payments.map(payment => `
                                    <tr class="${payment.confirmed ? 'confirmed' : 'pending'}">
                                        <td>${new Date(payment.payment_date).toLocaleDateString('es-ES')}</td>
                                        <td>XAF ${Number(payment.amount).toLocaleString('es-ES')}</td>
                                        <td><span class="status-badge ${payment.confirmed ? 'confirmed' : 'pending'}">${payment.confirmed ? 'Confirmado' : 'Pendiente'}</span></td>
                                        <td>XAF ${Number(payment.previous_balance).toLocaleString('es-ES')}</td>
                                        <td>XAF ${Number(payment.new_balance).toLocaleString('es-ES')}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        })
        .catch(() => {
            modalBody.innerHTML = `<div class="partner-details"><span style='color:red;'>Error al cargar los datos</span></div>`;
        });

    modal.style.display = 'block';
}

// Función para actualizar las estadísticas en tiempo real
function updateStatistics() {
    // Esta función podría ser utilizada para actualizar las estadísticas en tiempo real
    // si se implementa una funcionalidad de actualización automática
    console.log('Actualizando estadísticas...');
}

// Función para exportar datos
function exportData() {
    // Esta función podría ser utilizada para exportar los datos de beneficios
    // a un archivo CSV o PDF
    alert('Funcionalidad de exportación en desarrollo');
}

// Función para solicitar pago y generar PDF
function requestPayment(partnerName, amount) {
    // 1. Generar PDF de solicitud de pago
    const pdfUrl = `generate_payment_request.php?partner_name=${encodeURIComponent(partnerName)}&amount=${amount}`;
    window.open(pdfUrl, '_blank');

    // 2. Abrir WhatsApp Web con mensaje pre-rellenado
    const whatsappNumber = '240222374204'; // Sin el '+'
    const message = `Hola, soy ${partnerName}. Adjunto mi solicitud de pago de beneficios por un monto de XAF ${amount.toLocaleString('es-GQ', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}. Por favor, revisen el PDF adjunto.`;
    const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${encodeURIComponent(message)}`;
    
    // Dar un pequeño retraso para que el PDF tenga tiempo de empezar a descargarse
    setTimeout(() => {
        window.open(whatsappUrl, '_blank');
    }, 1000); // 1 segundo de retraso
}

// Función para confirmar pago
function confirmPayment(partnerName, amountPaid) {
    if (!confirm(`¿Estás seguro de que quieres confirmar el pago de XAF ${amountPaid.toLocaleString('es-GQ', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} a ${partnerName}?`)) {
        return; // User cancelled
    }

    // Make an AJAX request to confirm_payment.php
    fetch('confirm_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `partner_name=${encodeURIComponent(partnerName)}&amount_paid=${amountPaid}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload(); // Reload the page to show updated balances
        } else {
            alert('Error al confirmar el pago: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ocurrió un error al procesar la solicitud.');
    });
}
# Opened Files
## File Name
includes\header.php
## File Content
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Abeme Modjobuy - Envíos entre Ghana y Guinea Ecuatorial</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/shipments.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/forms.css">
    <link rel="stylesheet" href="css/shipment-groups.css">
    <link rel="stylesheet" href="css/notifications.css">
    <link rel="stylesheet" href="css/visibility-improvements.css">
    <link rel="stylesheet" href="css/pagination.css">
    <link rel="stylesheet" href="css/table-scroll.css">
    <link rel="stylesheet" href="css/ajax-loading.css">
    <link rel="stylesheet" href="css/footer-responsive.css">
    <link rel="stylesheet" href="css/group-tables-mobile.css">
    <link rel="stylesheet" href="css/payment-details.css">
    <link rel="stylesheet" href="css/archived-shipments.css">
    <script src="js/menu.js" defer></script>
    <script src="js/shipments.js" defer></script>
    <script src="js/shipment-groups.js" defer></script>
    <script src="js/notifications.js" defer></script>
    <script src="js/tracking.js" defer></script>
    <script src="js/payment-calculator.js" defer></script>
    <script src="js/notify-arrival.js" defer></script>
</head>
<body>
    <!-- Encabezado -->
    <header class="header">
        <nav class="nav-container">
            <a href="index.php" class="logo">
                <img src="img/logo.png" alt="AME Logo" class="logo-img">
            </a>
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <ul class="nav-menu" id="navMenu">
                <li><a href="index.php" class="nav-link"><i class="fas fa-home"></i><span>Inicio</span></a></li>
                <li><a href="Rotteri nza kus web/index.php" class="nav-link"><i class="fas fa-shopping-cart"></i><span>Compras</span></a></li>
                <li><a href="track_shipment.php" class="nav-link"><i class="fas fa-search"></i><span>Rastrear</span></a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li><a href="admin.php" class="nav-link"><i class="fas fa-cog"></i><span>Admin</span></a></li>
                <li><a href="benefits.php" class="nav-link"><i class="fas fa-chart-bar"></i><span>Beneficios</span></a></li>
                <li><a href="expenses.php" class="nav-link"><i class="fas fa-money-bill-wave"></i><span>Gastos</span></a></li>
                <li><a href="archived_shipments.php" class="nav-link"><i class="fas fa-box-archive"></i><span>Entregas</span></a></li>
                <li>
                    <div class="notification-bell">
                        <a href="#" id="notificationBell" class="nav-link">
                            <i class="fas fa-bell"></i>
                            <span id="notificationCount" class="notification-badge" style="display: none;">0</span>
                        </a>
                        <div id="notificationList" class="notification-list"></div>
                    </div>
                </li>
                <li><a href="logout.php" class="nav-link"><i class="fas fa-sign-out-alt"></i> Salir</a></li>
                <?php else: ?>
                <li><a href="login.php" class="nav-link"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    
    <main class="main-content">
# Opened Files
## File Name
includes\functions.php
## File Content

<?php
// Obtener todos los envíos activos (no entregados)
function getAllShipments($pdo) {
    $stmt = $pdo->query("SELECT * FROM shipments WHERE status != 'delivered' ORDER BY created_at DESC, ship_date DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener todos los envíos incluyendo entregados (para reportes)
function getAllShipmentsIncludingDelivered($pdo) {
    $stmt = $pdo->query("SELECT * FROM shipments ORDER BY created_at DESC, ship_date DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Buscar envío por código

function findShipmentByCode($pdo, $code) {
    $stmt = $pdo->prepare("SELECT * FROM shipments WHERE code = ?");
    $stmt->execute([$code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Buscar envío por ID
function getShipmentById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM shipments WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Crear nuevo envío
function generateShipmentCode($pdo) {
    do {
        // Genera un código con el formato ABM-XXXXXX
        $number = mt_rand(100000, 999999);
        $code = 'ABM-' . $number;
        $stmt = $pdo->prepare("SELECT id FROM shipments WHERE code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetch());
    
    return $code;
}

function generateGroupCode($date) {
    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $year = date('y', $timestamp);
    
    // Array de meses en español
    $meses = [
        1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
    ];
    
    $mes = $meses[intval(date('n', $timestamp))];
    
    // Formato: mes(en letras)-dia-año
    return $mes . '-' . $day . '-' . $year;
}

// Obtener envíos agrupados por fecha
function getShipmentsByGroup($pdo) {
    // Asegurarse de que todos los group_codes existan en shipment_groups
    $pdo->query("
        INSERT IGNORE INTO shipment_groups (group_code)
        SELECT DISTINCT group_code FROM shipments 
        WHERE group_code IS NOT NULL 
        AND group_code NOT IN (SELECT group_code FROM shipment_groups)
    ");

    // Actualizar automáticamente el estado de archivado de los grupos
    $pdo->query("
        UPDATE shipment_groups sg
        SET is_archived = 1
        WHERE NOT EXISTS (
            SELECT 1 FROM shipments s 
            WHERE s.group_code = sg.group_code 
            AND s.status != 'delivered'
        )
    ");

    $stmt = $pdo->query("
        SELECT 
            s.group_code,
            DATE_FORMAT(s.ship_date, '%d/%m/%Y') as formatted_date,
            COUNT(*) as total_shipments,
            SUM(CASE WHEN s.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN s.status = 'ontheway' THEN 1 ELSE 0 END) as ontheway,
            SUM(CASE WHEN s.status = 'arrived' THEN 1 ELSE 0 END) as arrived,
            SUM(CASE WHEN s.status = 'delay' THEN 1 ELSE 0 END) as delayed_count,
            SUM(CASE WHEN s.status = 'delivered' THEN 1 ELSE 0 END) as delivered
        FROM shipments s
        LEFT JOIN shipment_groups sg ON s.group_code = sg.group_code
        WHERE s.status != 'delivered'
        GROUP BY s.group_code, s.ship_date
        HAVING COUNT(*) > 0
        ORDER BY s.ship_date DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener envíos de un grupo específico
function getShipmentsByGroupCode($pdo, $groupCode) {
    $stmt = $pdo->prepare("
        SELECT s.* 
        FROM shipments s
        LEFT JOIN shipment_groups sg ON s.group_code = sg.group_code
        WHERE s.group_code = ? 
        AND s.status != 'delivered'
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$groupCode]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function createShipment($pdo, $data) {
    try {
        if (!isset($data['code'])) {
            $data['code'] = generateShipmentCode($pdo);
        }
        
        // Validar y establecer un estado predeterminado si es necesario
        if (!isset($data['status']) || empty($data['status'])) {
            $data['status'] = 'pending';
        }
        
        $stmt = $pdo->prepare("INSERT INTO shipments (code, group_code, sender_name, sender_phone, receiver_name, receiver_phone, product, weight, shipping_cost, sale_price, advance_payment, profit, ship_date, est_date, status)
                                VALUES (:code, :group_code, :sender_name, :sender_phone, :receiver_name, :receiver_phone, :product, :weight, :shipping_cost, :sale_price, :advance_payment, :profit, :ship_date, :est_date, :status)");
        
        if ($stmt->execute($data)) {
            return $pdo->lastInsertId();
        } else {
            // Throw an exception if execute fails, to be caught by the catch block
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Error al ejecutar la inserción: " . ($errorInfo[2] ?? 'Unknown error'));
        }
    } catch (PDOException $e) {
        // Temporarily rethrow the original exception for debugging
        throw $e;
    } catch (Exception $e) {
        // Log other exceptions
        error_log("General Error in createShipment: " . $e->getMessage());
        // Rethrow the exception to be handled by the caller
        throw $e;
    }
}

// Actualizar estado de envío
function updateShipmentStatus($pdo, $id, $status) {
    // Validar que el estado no esté vacío y sea válido
    $validStatus = ['pending', 'ontheway', 'arrived', 'delayed', 'delivered'];
    
    if (empty($status) || !in_array($status, $validStatus)) {
        $status = 'pending'; // Usar 'pending' como valor predeterminado
    }
    
    // Obtener el estado actual del envío
    $stmt = $pdo->prepare("SELECT status FROM shipments WHERE id = ?");
    $stmt->execute([$id]);
    $currentStatus = $stmt->fetchColumn();
    
    // Actualizar el estado del envío
    $stmt = $pdo->prepare("UPDATE shipments SET status = ? WHERE id = ?");
    $result = $stmt->execute([$status, $id]);
    
    // Si el estado cambia a "delivered", actualizar los beneficios
    if ($result && $status === 'delivered' && $currentStatus !== 'delivered') {
        updateBenefitsOnDelivery($pdo, $id);
    }
    
    return $result;
}

// Actualizar envío
function updateShipment($pdo, $id, $data) {
    try {
        // Calcular el precio de venta basado en el peso
        if (isset($data['weight'])) {
            $price_per_kg = 6500; // Precio por kilo en XAF (Franco CFA de África Central)
            $weight = floatval($data['weight']);
            $data['sale_price'] = $weight * $price_per_kg;
        }

        // Validar y establecer un estado predeterminado si es necesario
        if (!isset($data['status']) || empty($data['status'])) {
            $data['status'] = 'pending';
        }

        // Validar que el estado sea válido
        $validStatus = ['pending', 'ontheway', 'arrived', 'delayed', 'delivered'];
        if (!in_array($data['status'], $validStatus)) {
            $data['status'] = 'pending';
        }

        $sql = "UPDATE shipments SET
                sender_name = :sender_name,
                sender_phone = :sender_phone,
                receiver_name = :receiver_name,
                receiver_phone = :receiver_phone,
                product = :product,
                weight = :weight,
                sale_price = :sale_price,
                advance_payment = :advance_payment,
                ship_date = :ship_date,
                est_date = :est_date,
                status = :status
                WHERE id = :id";
        
        $data['id'] = $id;
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($data);
        
        if ($result) {
            // Obtener los datos actualizados
            $stmt = $pdo->prepare("SELECT * FROM shipments WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    } catch (PDOException $e) {
        error_log("Error updating shipment: " . $e->getMessage());
        return false;
    }
}

// Eliminar envío
function deleteShipment($pdo, $id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM shipments WHERE id = ?");
        $result = $stmt->execute([$id]);
        if (!$result) {
            error_log("Error deleting shipment: " . print_r($stmt->errorInfo(), true));
            return false;
        }
        return true;
    } catch (PDOException $e) {
        error_log("Exception deleting shipment: " . $e->getMessage());
        return false;
    }
}

// Validar usuario
function createAdminIfNotExists($pdo) {
    // Verificar si la tabla users existe
    try {
        $stmt = $pdo->query("SELECT 1 FROM users LIMIT 1");
    } catch (PDOException $e) {
        // La tabla no existe, crearla
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) NOT NULL DEFAULT 'user'
        )");
        
        // Crear usuario administrador por defecto
        $email = 'admin@admin.com';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, 'admin')");
        $stmt->execute([$email, $password]);
        
        return true;
    }
    return false;
}

function authenticateUser($pdo, $email, $password) {
    $stmt = $pdo->prepare("SELECT id, email, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']); // Don't store password in session
        return $user;
    }
    
    return false;
}

// Obtener el badge de estado para mostrar
function getStatusBadge($status) {
    $statusMap = [
        'pending' => ['class' => 'status-pending', 'text' => 'Pendiente'],
        'ontheway' => ['class' => 'status-ontheway', 'text' => 'En Camino'],
        'arrived' => ['class' => 'status-arrived', 'text' => 'Llegada'],
        'delayed' => ['class' => 'status-delayed', 'text' => 'Retraso'],
        'delivered' => ['class' => 'status-delivered', 'text' => 'Entregado']
    ];
    
    // Si el estado es vacío o no existe en el mapa, usar 'pending' como valor predeterminado
    if (empty($status) || !isset($statusMap[$status])) {
        $status = 'pending';
    }
    
    return '<span class="status '.$statusMap[$status]['class'].'">'.$statusMap[$status]['text'].'</span>';
}

// --- Funciones de Administración de Usuarios ---

// Obtener todos los administradores
function getAllAdmins($pdo) {
    $stmt = $pdo->query("SELECT id, email, created_at FROM users");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Crear nuevo administrador
function createAdmin($pdo, $email, $password) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    return $stmt->execute([$email, $hashed_password]);
}

// Eliminar administrador
function deleteAdmin($pdo, $id) {
    // Prevenir la eliminación del usuario principal (ID 1)
    if ($id == 1) {
        return false;
    }
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    return $stmt->execute([$id]);
}

function getRecentUndeliveredShipments($pdo, $sortDirection = 'DESC', $page = 1, $perPage = 10) {
    // Calcular el offset
    $offset = ($page - 1) * $perPage;
    
    // Obtener el total de registros
    $countStmt = $pdo->query("SELECT COUNT(*) FROM shipments WHERE status != 'delivered'");
    $totalRecords = $countStmt->fetchColumn();
    
    // Obtener los envíos para la página actual
    $stmt = $pdo->prepare("
        SELECT code, weight, ship_date, sale_price, advance_payment, status
        FROM shipments
        WHERE status != 'delivered'
        ORDER BY ship_date " . ($sortDirection === 'ASC' ? 'ASC' : 'DESC') . "
        LIMIT :limit OFFSET :offset
    ");
    
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return [
        'shipments' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'total' => $totalRecords,
        'pages' => ceil($totalRecords / $perPage),
        'current_page' => $page
    ];
}

function getShipmentByCode($pdo, $code) {
    $stmt = $pdo->prepare("SELECT * FROM shipments WHERE code = ?");
    $stmt->execute([$code]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Función para agregar un gasto o ingreso
function addExpense($pdo, $description, $amount, $paid_by, $date, $operation_type) {
    try {
        // Insertar el registro en la tabla de gastos (o ingresos)
        $stmt = $pdo->prepare("INSERT INTO expenses (description, amount, paid_by, date, operation_type) VALUES (?, ?, ?, ?, ?)");
        $insert_success = $stmt->execute([$description, $amount, $paid_by, $date, $operation_type]);

        if (!$insert_success) {
            error_log("Error inserting expense: " . print_r($stmt->errorInfo(), true));
            return false;
        }

        $update_success = true; // Flag to track success of subsequent updates

        if ($operation_type === 'subtract') {
            // Restar del beneficio total del sistema
            $stmt = $pdo->prepare("UPDATE system_metrics SET metric_value = metric_value - ? WHERE metric_name = 'total_accumulated_benefits'");
            $update_success = $stmt->execute([$amount]);
            if (!$update_success) {
                error_log("Error updating system_metrics (subtract): " . print_r($stmt->errorInfo(), true));
                return false;
            }

            // Sumar al beneficio del socio que puso el dinero (como reembolso/contribución)
            $stmt = $pdo->prepare("UPDATE partner_benefits SET total_expenses = total_expenses + ?, current_balance = current_balance + ? WHERE partner_name = ?");
            $update_success = $stmt->execute([$amount, $amount, $paid_by]);
            if (!$update_success) {
                error_log("Error updating partner_benefits (subtract): " . print_r($stmt->errorInfo(), true));
                return false;
            }
        } elseif ($operation_type === 'add') {
            // Sumar al beneficio total del sistema
            $stmt = $pdo->prepare("UPDATE system_metrics SET metric_value = metric_value + ? WHERE metric_name = 'total_accumulated_benefits'");
            $update_success = $stmt->execute([$amount]);
            if (!$update_success) {
                error_log("Error updating system_metrics (add): " . print_r($stmt->errorInfo(), true));
                return false;
            }
        } elseif ($operation_type === 'adjust') {
            // Restar solo del beneficio total del sistema (ajuste)
            $stmt = $pdo->prepare("UPDATE system_metrics SET metric_value = metric_value - ? WHERE metric_name = 'total_accumulated_benefits'");
            $update_success = $stmt->execute([$amount]);
            if (!$update_success) {
                error_log("Error updating system_metrics (adjust): " . print_r($stmt->errorInfo(), true));
                return false;
            }
        }

        return true; // All operations successful
    } catch (PDOException $e) {
        error_log("PDOException in addExpense: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log("General Exception in addExpense: " . $e->getMessage());
        return false;
    }
}

// Función para actualizar beneficios cuando un envío es entregado
function updateBenefitsOnDelivery($pdo, $shipment_id) {
    try {
        // Obtener el envío
        $stmt = $pdo->prepare("SELECT * FROM shipments WHERE id = ?");
        $stmt->execute([$shipment_id]);
        $shipment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$shipment) {
            return false;
        }
        
        // Calcular el beneficio (asumimos un 20% del precio de venta)
        $benefit = $shipment['sale_price'] * 0.20;

        // --- NEW CODE: Update system-wide total benefits ---
        $stmt = $pdo->prepare("UPDATE system_metrics SET metric_value = metric_value + ? WHERE metric_name = 'total_accumulated_benefits'");
        $stmt->execute([$benefit]);
        // --- END NEW CODE ---

        // Definir los socios y sus porcentajes
        $partners = [
            'FERNANDO CHALE' => 18,
            'MARIA CARMEN NSUE' => 18,
            'GENEROSA ABEME' => 30,
            'MARIA ISABEL' => 8,
            'CAJA' => 16,
            'FONDOS DE SOCIOS' => 10
        ];
        
        // Distribuir el beneficio entre los socios
        foreach ($partners as $partner => $percentage) {
            $partner_benefit = $benefit * ($percentage / 100);
            
            // Actualizar los beneficios del socio
            $stmt = $pdo->prepare("UPDATE partner_benefits SET total_benefits = total_benefits + ?, current_balance = current_balance + ? WHERE partner_name = ?");
            $stmt->execute([$partner_benefit, $partner_benefit, $partner]);
            
            // Registrar en el historial de beneficios
            $stmt = $pdo->prepare("INSERT INTO benefit_history (partner_name, shipment_id, amount, type, date) VALUES (?, ?, ?, 'benefit', ?)");
            $stmt->execute([$partner, $shipment_id, $partner_benefit, date('Y-m-d')]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error updating benefits: " . $e->getMessage());
        return false;
    }
}

// Función para obtener los beneficios de los socios
function getPartnerBenefits($pdo) {
    try {
        // Primero actualizamos las ganancias totales
        $pdo->query("CALL update_partner_total_earnings()");
        
        // Luego obtenemos los datos ordenados por total_earnings
        $stmt = $pdo->query("SELECT * FROM partner_benefits ORDER BY total_earnings DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting partner benefits: " . $e->getMessage());
        return [];
    }
}

// Función para obtener el historial de beneficios
function getBenefitHistory($pdo, $partner_name = null) {
    if ($partner_name) {
        $stmt = $pdo->prepare("SELECT * FROM benefit_history WHERE partner_name = ? ORDER BY date DESC, created_at DESC");
        $stmt->execute([$partner_name]);
    } else {
        $stmt = $pdo->query("SELECT * FROM benefit_history ORDER BY date DESC, created_at DESC");
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para obtener los gastos
function getExpenses($pdo) {
    $stmt = $pdo->query("SELECT * FROM expenses ORDER BY date DESC, created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
# Opened Files
## File Name
login.php
## File Content
<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Si el usuario ya está autenticado, redirigir a admin
if (isAuthenticated()) {
    header('Location: admin.php');
    exit;
}

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $user = authenticateUser($pdo, $email, $password);
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        header('Location: admin.php');
        exit;
    } else {
        $error = "Credenciales incorrectas. Por favor, inténtalo de nuevo.";
        
    }
}
?>

<?php include 'includes/header.php'; ?>

    <section class="login-container">
        <h2 class="section-title">Iniciar Sesión</h2>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form id="login-form" method="POST">
            <div class="form-group">
                <label for="login-email">Correo electrónico *</label>
                <input type="email" id="login-email" name="email" required>
            </div>
            <div class="form-group">
                <label for="login-password">Contraseña *</label>
                <input type="password" id="login-password" name="password" required>
            </div>
            <button type="submit" class="btn">Acceder</button>
        </form>
    </section>

<?php include 'includes/footer.php'; ?>

# Opened Files
## File Name
index.php
## File Content
<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
?>

<?php include 'includes/header.php'; ?>

    <!-- Sección Hero -->
    <section class="hero section">
        <div class="container">
            <div class="hero-content">
                <h2 class="section-title">Conectando Ghana y Guinea Ecuatorial</h2>
                <p>Servicio de envíos confiable y eficiente entre dos naciones</p>
                <a href="#tracking" class="btn btn-lg">Rastrear mi envío</a>
            </div>
        </div>
    </section>

    <!-- Sección de Seguimiento -->
    <section id="tracking" class="section">
        <div class="container">
            <h2 class="section-title">Seguimiento de Envíos</h2>
            <div class="tracking-form">
                <input 
                    type="text" 
                    class="tracking-input form-input" 
                    id="tracking-code" 
                    placeholder="Ingresa tu código de seguimiento (Ej: ABM-123456)"
                    pattern="ABM-[0-9]{6}"
                    title="El código debe tener el formato ABM-123456">
                <button class="btn" onclick="trackShipment()">
                    <i class="fas fa-search"></i> Buscar
                </button>
            </div>
            <small class="form-help">Ingrese el código en el formato ABM-XXXXXX donde X son números</small>
            <div id="tracking-result" class="tracking-result">
                <h3 class="section-title">Resultados de seguimiento:</h3>
                <div style="overflow-x:auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Peso (kg)</th>
                            <th>Precio</th>
                            <th>Pago Adelantado</th>
                            <th>Saldo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody id="tracking-result-body">
                        <!-- Resultados se llenarán con JavaScript -->
                    </tbody>
                </table>
                </div>
            </div>
            <h3 class="section-title" style="margin-top:2.5rem;">
                Ejemplos de envíos recientes
                <div class="sort-controls">
                    <button onclick="toggleSort()" class="sort-btn">
                        <i class="fas fa-sort"></i> Cambiar orden
                    </button>
                </div>
            </h3>
            <div class="recent-shipments-container">
                <div class="table-scroll-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Peso (kg)</th>
                                <th>Fecha de envío</th>
                                <th>Precio</th>
                                <th>Pago Adelantado</th>
                                <th>Saldo</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="recent-shipments">
                            <?php
                            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                            $sort = isset($_GET['sort']) ? $_GET['sort'] : 'DESC';
                            $perPage = 10;
                    
                    $result = getRecentUndeliveredShipments($pdo, $sort, $page, $perPage);
                    $shipments = $result['shipments'];
                    
                    foreach ($shipments as $shipment) {
                        echo '<tr>';
                        echo '<td>'.$shipment['weight'].' kg</td>';
                        echo '<td>'.date('d/m/Y', strtotime($shipment['ship_date'])).'</td>';
                        echo '<td>'.number_format($shipment['sale_price'], 0, '.', ',').' XAF</td>';
                        echo '<td>'.number_format($shipment['advance_payment'], 2, '.', ',').' XAF</td>';
                        echo '<td>'.number_format($shipment['sale_price'] - $shipment['advance_payment'], 2, '.', ',').' XAF</td>';
                        echo '<td>'.getStatusBadge($shipment['status']).'</td>';
                        echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
                </div>
            </div>
            
            <?php if ($result['pages'] > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo ($page - 1); ?>&sort=<?php echo $sort; ?>" class="page-link">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                <?php endif; ?>
                
                <div class="page-numbers">
                    <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&sort=<?php echo $sort; ?>"
                        class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                
                <?php if ($page < $result['pages']): ?>
                    <a href="?page=<?php echo ($page + 1); ?>&sort=<?php echo $sort; ?>" class="page-link">
                        Siguiente <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
                
                <div class="results-info">
                    Mostrando <?php echo count($shipments); ?> de <?php echo $result['total']; ?> envíos
                </div>
            </div>
            <?php endif; ?>
    </section>

    <!-- Sección de Contacto -->
    <section id="contact" class="contact-section">
        <div class="contact-container">
            <div class="contact-info">
                <h3>Contacta con Nosotros</h3>
                <div class="contact-details">
                    <p><i class="fas fa-map-marker-alt"></i> Accra Office: 123 Independence Ave, Accra, Ghana</p>
                    <p><i class="fas fa-map-marker-alt"></i> Malabo Office: Calle de la Libertad 45, Malabo, Guinea Ecuatorial</p>
                    <p><i class="fas fa-phone"></i> +233 24 123 4567 (Ghana)</p>
                    <p><i class="fas fa-phone"></i> +240 222 123 456 (Guinea Ecuatorial)</p>
                    <p><i class="fas fa-envelope"></i> .com</p>
                </div>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div class="contact-form">
                <form id="contactForm" action="send_contact.php" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="name">Nombre completo *</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Correo electrónico *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Número de teléfono *</label>
                        <input type="tel" id="phone" name="phone" required>
                    </div>
                    <div class="form-group">
                        <label for="subject">Asunto *</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Mensaje *</label>
                        <textarea id="message" name="message" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="attachment">Adjuntar archivo (opcional)</label>
                        <input type="file" id="attachment" name="attachment" class="file-input" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                    <button type="submit" class="btn">Enviar Mensaje</button>
                </form>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>
# Opened Files
## File Name
expenses.php
## File Content
<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Verificar que el usuario esté autenticado
requireAuth();

// Definir los socios
$partners = [
    'FERNANDO CHALE',
    'MARIA CARMEN NSUE',
    'GENEROSA ABEME',
    'MARIA ISABEL',
    'CAJA',
    'FONDOS DE SOCIOS'
];

// Procesar formulario de nuevo gasto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $description = $_POST['description'];
    $amount = floatval($_POST['amount']);
    $paid_by = $_POST['paid_by'];
    $date = $_POST['date'];
    $operation_type = $_POST['operation_type'] ?? 'subtract'; // Default to subtract if not set
    
    if (addExpense($pdo, $description, $amount, $paid_by, $date, $operation_type)) {
        $success = "Gasto registrado correctamente.";
    } else {
        $error = "Error al registrar el gasto.";
    }
    
    // Recargar la página para mostrar los cambios
    header("Location: expenses.php?success=" . urlencode($success));
    exit;
}

// Obtener gastos recientes
$expenses = getExpenses($pdo);

// Obtener beneficios de los socios
$partnerBenefits = getPartnerBenefits($pdo);

include 'includes/header.php';
?>

<div class="container">
    <h1>Gestión de Gastos</h1>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
    <?php endif; ?>
    
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <!-- Formulario para añadir gasto -->
    <div class="card">
        <h2>Añadir Nuevo Gasto</h2>
        <form method="POST">
            <input type="hidden" name="add_expense" value="1">
            
            <div class="form-group">
                <label>Tipo de Operación *</label><br>
                <input type="radio" id="operation_subtract" name="operation_type" value="subtract" checked>
                <label for="operation_subtract">Restar (Gasto)</label><br>
                <input type="radio" id="operation_add" name="operation_type" value="add">
                <label for="operation_add">Añadir (Ingreso)</label><br>
                <input type="radio" id="operation_adjust" name="operation_type" value="adjust">
                <label for="operation_adjust">Ajustes (Restar solo del total)</label>
            </div>
            
            <div class="form-group">
                <label for="description">Descripción del Gasto *</label>
                <input type="text" id="description" name="description" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="amount">Monto (XAF) *</label>
                <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="paid_by">Pagado por *</label>
                <select id="paid_by" name="paid_by" class="form-control" required>
                    <option value="">Seleccione un socio</option>
                    <?php foreach ($partners as $partner): ?>
                        <option value="<?php echo htmlspecialchars($partner); ?>"><?php echo htmlspecialchars($partner); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="date">Fecha *</label>
                <input type="date" id="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Registrar Gasto</button>
        </form>
    </div>
    
    <!-- Resumen de beneficios -->
    <div class="card">
        <h2>Resumen de Beneficios</h2>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Socio</th>
                        <th>Gastos Totales</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($partnerBenefits as $benefit): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($benefit['partner_name']); ?></td>
                            <td>XAF <?php echo number_format($benefit['total_expenses'], 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Historial de gastos -->
    <div class="card">
        <h2>Historial de Gastos</h2>
        <div class="table-responsive">
            <?php if (empty($expenses)): ?>
                <p>No hay gastos registrados.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo de Operación</th>
                            <th>Monto</th>
                            <th>Descripción</th>
                            <th>Socio (si aplica)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($expense['date']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($expense['operation_type'])); ?></td>
                                <td>XAF <?php echo number_format($expense['amount'], 2, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($expense['description']); ?></td>
                                <td>
                                    <?php
                                    if (isset($expense['operation_type']) && $expense['operation_type'] === 'subtract') {
                                        echo htmlspecialchars($expense['paid_by']);
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
# Opened Files
## File Name
benefits.php
## File Content
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1976d2">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Sistema de Beneficios - Abeme Modjobuy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/benefits-app.css">
    <link rel="stylesheet" href="css/payment-history.css">
</head>
<body>
    <?php
    require_once 'includes/db.php';
    require_once 'includes/auth.php';
    require_once 'includes/functions.php';

    // Verificar que el usuario esté autenticado
    requireAuth();

    // Definir los socios y sus porcentajes
    $partners = [
        'FERNANDO CHALE' => 18,
        'MARIA CARMEN NSUE' => 18,
        'GENEROSA ABEME' => 30,
        'MARIA ISABEL' => 8,
        'CAJA' => 16,
        'FONDOS DE SOCIOS' => 10
    ];


    // Calcular ingresos totales (suma de lo que los clientes han pagado)
    $stmt = $pdo->query("SELECT SUM(sale_price) as total_revenue, SUM(weight) as total_kilos, COUNT(*) as total_shipments FROM shipments WHERE status = 'delivered'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalRevenue = $result['total_revenue'] ?? 0;
    $totalKilos = $result['total_kilos'] ?? 0;
    $totalShipments = $result['total_shipments'] ?? 0;

    // Beneficio base = kilos entregados * 2500
    $baseProfit = $totalKilos * 2500;

    // Obtener ingresos adicionales (operaciones tipo 'add')
    $stmt = $pdo->query("SELECT SUM(amount) as additional_profit FROM expenses WHERE operation_type = 'add'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $additionalProfit = $result['additional_profit'] ?? 0;

    // Beneficio total = beneficio base + ingresos adicionales
    $totalProfit = $baseProfit + $additionalProfit;

    // Calcular gastos totales (solo operaciones subtract y adjust)
    $stmt = $pdo->query("SELECT SUM(amount) as total_expenses FROM expenses WHERE operation_type IN ('subtract', 'adjust')");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalExpenses = $result['total_expenses'] ?? 0;

    // Beneficio neto = beneficio total - gastos
    $netProfit = $totalProfit - $totalExpenses;
    ?>

    <!-- Encabezado -->
    <header class="header">
        <nav class="nav-container">
            <a href="index.php" class="logo">
                <img src="img/logo.png" alt="AME Logo" class="logo-img">
            </a>
            <div class="header-title">
                <h1>Sistema de Beneficios</h1>
                <p>Distribución de ganancias entre socios</p>
            </div>
            <a href="admin.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </nav>
    </header>

    <main class="main-content">
        <section class="dashboard-section">
            <div class="stats-summary">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Ingresos Totales</h3>
                        <p class="stat-value">XAF <?php echo number_format($totalRevenue, 2, ',', '.'); ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Beneficios Totales</h3>
                        <p class="stat-value">XAF <?php echo number_format($totalProfit, 2, ',', '.'); ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Envíos Entregados</h3>
                        <p class="stat-value"><?php echo $totalShipments; ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-weight-hanging"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Kilos Entregados</h3>
                        <p class="stat-value"><?php echo number_format($totalKilos, 2, ',', '.'); ?> kg</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Gastos Totales</h3>
                        <p class="stat-value">XAF <?php echo number_format($totalExpenses, 2, ',', '.'); ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Beneficio Neto</h3>
                        <p class="stat-value">XAF <?php echo number_format($netProfit, 2, ',', '.'); ?></p>
                    </div>
                </div>
            </div>
            </div>

            <div class="benefits-distribution">
                <div class="chart-section">
                    <h2>Distribución de Beneficios</h2>
                    <div class="distribution-chart">
                        <canvas id="benefitsChart"></canvas>
                    </div>
                    <script>
                        window.partners = <?php echo json_encode($partners); ?>;
                    </script>
                </div>
                <?php
                // Definir colores para cada socio
                $partnerColors = [
                    'FERNANDO CHALE' => '#1976d2',      // Azul principal
                    'MARIA CARMEN NSUE' => '#2196f3',   // Azul secundario
                    'GENEROSA ABEME' => '#0d47a1',      // Azul oscuro
                    'MARIA ISABEL' => '#64b5f6',        // Azul claro
                    'CAJA' => '#90caf9',                // Azul más claro
                    'FONDOS DE SOCIOS' => '#bbdefb'     // Azul muy claro
                ];
                ?>
                <div class="partners-progress">
                    <?php foreach ($partners as $name => $percentage): ?>
                        <?php
                        $amount = $netProfit * ($percentage / 100);
                        $isMainPartner = in_array($name, ['FERNANDO CHALE', 'MARIA CARMEN NSUE', 'GENEROSA ABEME']);
                        $color = $partnerColors[$name];
                        ?>
                        <div class="partner-progress-card <?php echo $isMainPartner ? 'main-partner' : ''; ?>">
                            <div class="progress-header">
                                <div class="progress-info">
                                    <span class="partner-name"><?php echo $name; ?></span>
                                    <div class="progress-stats">
                                        <span class="percentage-badge"><?php echo $percentage; ?>%</span>
                                        <span class="amount">XAF <?php echo number_format($amount, 2, ',', '.'); ?></span>
                                    </div>
                                </div>
                                <?php if ($isMainPartner): ?>
                                    <span class="main-partner-tag">Principal</span>
                                <?php endif; ?>
                            </div>
                            <div class="progress-container">
                                <div class="progress-track">
                                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%; background: <?php echo $color; ?>">
                                        <div class="progress-glow" style="background: <?php echo $color; ?>"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="detailed-view">
                <h2>Detalle de Beneficios por Socio</h2>
                <div class="table-responsive">
                    <table class="benefits-table">
                        <thead>
                            <tr>
                                <th>Socio</th>
                                <th>Porcentaje</th>
                                <th>Monto (XAF)</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($partners as $name => $percentage): ?>
                            <?php 
                            $amount = $netProfit * ($percentage / 100);
                            $isMainPartner = in_array($name, ['FERNANDO CHALE', 'MARIA CARMEN NSUE', 'GENEROSA ABEME']);
                            ?>
                            <tr class="<?php echo $isMainPartner ? 'main-partner-row' : ''; ?>">
                                <td>
                                    <div class="partner-info">
                                        <span class="partner-name"><?php echo $name; ?></span>
                                        <?php if ($isMainPartner): ?>
                                            <span class="main-partner-badge">Principal</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo $percentage; ?>%</td>
                                <td>XAF <?php echo number_format($amount, 2, ',', '.'); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-primary" onclick="viewDetails('<?php echo $name; ?>')">
                                            <i class="fas fa-eye"></i> Detalles
                                        </button>
                                        <button class="btn btn-success" onclick="requestPayment('<?php echo htmlspecialchars($name); ?>', <?php echo $amount; ?>)">
                                            <i class="fab fa-whatsapp"></i> Solicitar
                                        </button>
                                        <button class="btn btn-info" onclick="confirmPayment('<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>', <?php echo $amount; ?>)">
                                            <i class="fas fa-check-circle"></i> Confirmar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>

    <!-- Modal para detalles -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Detalles del Socio</h2>
                <span class="close">&times;</span>
            </div>
            <div id="modalBody" class="modal-body">
                <div class="partner-details">
                    <div class="partner-header">
                        <h3 id="partnerName"></h3>
                    </div>
                    <div class="partner-summary">
                        <div class="summary-card">
                            <h4>Ganancias Totales</h4>
                            <p id="partnerTotalEarnings"></p>
                        </div>
                        <div class="summary-card">
                            <h4>Balance Actual</h4>
                            <p id="partnerCurrentBalance"></p>
                        </div>
                    </div>
                </div>

                <div class="payment-history">
                    <h4>Historial de Pagos</h4>
                    <div class="table-responsive">
                        <table id="paymentsHistory" class="table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th>Balance Anterior</th>
                                    <th>Nuevo Balance</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="js/benefits.js"></script>
</body>
</html>
# Opened Files
## File Name
includes\partner_earnings.php
## File Content
<?php
require_once '../includes/db.php';

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

        if (!$percentage) {
            throw new Exception("No se encontró el porcentaje para el socio");
        }

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

# Opened Files
## File Name
confirm_payment.php
## File Content
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
# Opened Files
## File Name
css\payment-history.css
## File Content
/* Estilos para el modal de detalles */
.partner-header {
    text-align: center;
    margin-bottom: 2rem;
}

.partner-header h3 {
    font-size: 1.5rem;
    color: var(--primary-color);
    margin: 0;
    padding: 0;
}

.partner-summary {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.summary-card {
    background: #fff;
    border-radius: 8px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.summary-card h4 {
    color: var(--text-secondary);
    margin: 0 0 1rem 0;
    font-size: 1rem;
    font-weight: 500;
}

.summary-card p {
    color: var(--primary-color);
    font-size: 1.5rem;
    font-weight: 600;
    margin: 0;
}

.payment-history {
    margin: 2rem 0;
}

.payment-history h4 {
    color: var(--text-primary);
    margin-bottom: 1rem;
    font-size: 1.1rem;
}

.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.table th,
.table td {
    padding: 0.75rem;
    border-bottom: 1px solid #dee2e6;
    text-align: left;
}

.table th {
    background: #f8f9fa;
    font-weight: 500;
    color: var(--text-secondary);
}

.table tbody tr:hover {
    background: #f8f9fa;
}

.table tr.confirmed {
    background-color: rgba(76, 175, 80, 0.05);
}

.table tr.pending {
    background-color: rgba(255, 193, 7, 0.05);
}

.status-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-badge.confirmed {
    background-color: rgba(76, 175, 80, 0.1);
    color: #2e7d32;
}

.status-badge.pending {
    background-color: rgba(255, 193, 7, 0.1);
    color: #f57f17;
}

/* Responsive adjustments */
@media screen and (max-width: 768px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }

    .table {
        font-size: 0.8rem;
    }

    .table th,
    .table td {
        padding: 0.5rem;
    }

    .status-badge {
        font-size: 0.7rem;
    }
}

# Opened Files
## File Name
includes\partner_functions.php
## File Content
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

# Opened Files
## File Name
api\setup_partner_benefits.php
## File Content
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

# Opened Files
## File Name
sql\setup_partner_earnings.sql
## File Content
-- Crear tabla de beneficios de socios si no existe
CREATE TABLE IF NOT EXISTS `partner_benefits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `partner_name` VARCHAR(100) NOT NULL UNIQUE,
    `percentage` DECIMAL(5,2) NOT NULL,
    `total_earnings` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Ganancias totales históricas (solo incrementa)',
    `current_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Balance actual después de pagos',
    `role` ENUM('Principal', 'Socio', 'Caja', 'Fondos') NOT NULL DEFAULT 'Socio',
    `join_date` DATE NOT NULL,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Crear tabla de pagos a socios
CREATE TABLE IF NOT EXISTS `partner_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `partner_name` VARCHAR(100) NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `confirmed` BOOLEAN DEFAULT FALSE,
    `confirmation_date` TIMESTAMP NULL,
    `previous_balance` DECIMAL(15,2) NOT NULL COMMENT 'Balance antes del pago',
    `new_balance` DECIMAL(15,2) NOT NULL COMMENT 'Balance después del pago',
    `notes` TEXT,
    FOREIGN KEY (`partner_name`) REFERENCES `partner_benefits`(`partner_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Crear tabla de historial de beneficios
CREATE TABLE IF NOT EXISTS `partner_earnings_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `partner_name` VARCHAR(100) NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `source` VARCHAR(50) NOT NULL COMMENT 'shipment/expense',
    `source_id` INT NOT NULL COMMENT 'ID del envío o gasto',
    `earned_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `previous_total` DECIMAL(15,2) NOT NULL COMMENT 'Total anterior',
    `new_total` DECIMAL(15,2) NOT NULL COMMENT 'Nuevo total después de sumar',
    FOREIGN KEY (`partner_name`) REFERENCES `partner_benefits`(`partner_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trigger para actualizar total_earnings cuando se registra un nuevo beneficio
DELIMITER //
CREATE TRIGGER update_partner_earnings_after_history
AFTER INSERT ON partner_earnings_history
FOR EACH ROW
BEGIN
    UPDATE partner_benefits 
    SET 
        total_earnings = NEW.new_total,
        current_balance = current_balance + NEW.amount
    WHERE partner_name = NEW.partner_name;
END;
//

-- Trigger para actualizar current_balance cuando se confirma un pago
CREATE TRIGGER update_balance_after_payment_confirmation
AFTER UPDATE ON partner_payments
FOR EACH ROW
BEGIN
    IF NEW.confirmed = 1 AND OLD.confirmed = 0 THEN
        UPDATE partner_benefits
        SET current_balance = current_balance - NEW.amount
        WHERE partner_name = NEW.partner_name;
    END IF;
END;
//

-- Procedimiento para registrar un nuevo beneficio
CREATE PROCEDURE register_partner_earning(
    IN p_partner_name VARCHAR(100),
    IN p_amount DECIMAL(15,2),
    IN p_source VARCHAR(50),
    IN p_source_id INT
)
BEGIN
    DECLARE current_total DECIMAL(15,2);
    
    -- Obtener el total actual
    SELECT total_earnings INTO current_total
    FROM partner_benefits
    WHERE partner_name = p_partner_name;
    
    -- Registrar en el historial
    INSERT INTO partner_earnings_history (
        partner_name,
        amount,
        source,
        source_id,
        previous_total,
        new_total
    ) VALUES (
        p_partner_name,
        p_amount,
        p_source,
        p_source_id,
        current_total,
        current_total + p_amount
    );
END;
//

-- Procedimiento para actualizar beneficios de envío
CREATE PROCEDURE update_shipment_benefits(IN p_shipment_id INT)
BEGIN
    DECLARE total_benefit DECIMAL(15,2);
    
    -- Calcular beneficio total del envío
    SELECT (weight * 2500) INTO total_benefit
    FROM shipments
    WHERE id = p_shipment_id AND status = 'delivered';
    
    IF total_benefit > 0 THEN
        -- Distribuir beneficios entre socios
        INSERT INTO partner_earnings_history (
            partner_name,
            amount,
            source,
            source_id,
            previous_total,
            new_total
        )
        SELECT 
            pb.partner_name,
            (total_benefit * (pb.percentage / 100)) as benefit_amount,
            'shipment',
            p_shipment_id,
            pb.total_earnings,
            pb.total_earnings + (total_benefit * (pb.percentage / 100))
        FROM partner_benefits pb;
    END IF;
END;
//

DELIMITER ;

# Opened Files
## File Name
api\update_table_structure.php
## File Content
<?php
require_once '../includes/db.php';

try {
    // Verificar si la columna last_updated ya existe
    $stmt = $pdo->query("SHOW COLUMNS FROM partner_benefits LIKE 'last_updated'");
    $columnExists = $stmt->rowCount() > 0;

    if (!$columnExists) {
        // Agregar la columna last_updated
        $pdo->exec("
            ALTER TABLE partner_benefits 
            ADD COLUMN last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP 
            ON UPDATE CURRENT_TIMESTAMP
        ");
    }

    // Verificar otras columnas necesarias
    $stmt = $pdo->query("DESCRIBE partner_benefits");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Asegurarse de que todas las columnas necesarias existen
    $requiredColumns = [
        'partner_name' => "VARCHAR(100) NOT NULL",
        'percentage' => "DECIMAL(5,2) NOT NULL",
        'total_earnings' => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
        'current_balance' => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
        'role' => "ENUM('Principal', 'Socio', 'Caja', 'Fondos') NOT NULL DEFAULT 'Socio'",
        'join_date' => "DATE NOT NULL"
    ];

    foreach ($requiredColumns as $column => $definition) {
        if (!in_array($column, $columns)) {
            $pdo->exec("ALTER TABLE partner_benefits ADD COLUMN `$column` $definition");
        }
    }

    // Actualizar los datos existentes con valores por defecto si es necesario
    $pdo->exec("
        UPDATE partner_benefits 
        SET join_date = '2023-01-01' 
        WHERE join_date IS NULL
    ");

    // Verificar y actualizar la estructura de la tabla expenses
    $stmt = $pdo->query("DESCRIBE expenses");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Verificar y agregar las columnas necesarias
    $requiredColumns = [
        'partner_name' => "VARCHAR(100)",
        'notes' => "TEXT",
        'amount' => "DECIMAL(15,2) NOT NULL",
        'operation_type' => "ENUM('add', 'subtract', 'adjust') NOT NULL"
    ];

    foreach ($requiredColumns as $column => $definition) {
        if (!in_array($column, $columns)) {
            $pdo->exec("ALTER TABLE expenses ADD COLUMN `$column` $definition");
        }
    }

    // Si la tabla no existe, créala
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS expenses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            partner_name VARCHAR(100),
            amount DECIMAL(15,2) NOT NULL,
            operation_type ENUM('add', 'subtract', 'adjust') NOT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Verificar si la tabla partner_payments existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'partner_payments'");
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        // Crear la tabla partner_payments si no existe
        $pdo->exec("
            CREATE TABLE partner_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                partner_name VARCHAR(100) NOT NULL,
                amount DECIMAL(15,2) NOT NULL,
                payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                confirmation_date TIMESTAMP NULL,
                confirmed BOOLEAN DEFAULT FALSE,
                previous_balance DECIMAL(15,2) NOT NULL,
                new_balance DECIMAL(15,2) NOT NULL,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (partner_name) REFERENCES partner_benefits(partner_name)
            )
        ");
    } else {
        // Verificar y agregar columnas faltantes en partner_payments
        $stmt = $pdo->query("DESCRIBE partner_payments");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $requiredPaymentColumns = [
            'previous_balance' => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            'new_balance' => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            'notes' => "TEXT NULL",
            'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
            'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];

        foreach ($requiredPaymentColumns as $column => $definition) {
            if (!in_array($column, $columns)) {
                $pdo->exec("ALTER TABLE partner_payments ADD COLUMN `$column` $definition");
            }
        }
    }

    // Devolver éxito
    echo json_encode(['success' => true, 'message' => 'Estructura de tablas actualizada correctamente']);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al actualizar la estructura de las tablas: ' . $e->getMessage()]);
}

# Opened Files
## File Name
api\check_partner_tables.php
## File Content
<?php
require_once '../includes/db.php';

// Verificar si la tabla existe
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'partner_benefits'");
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        // Crear la tabla si no existe
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `partner_benefits` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `partner_name` VARCHAR(100) NOT NULL UNIQUE,
                `percentage` DECIMAL(5,2) NOT NULL,
                `total_earnings` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `current_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `role` ENUM('Principal', 'Socio', 'Caja', 'Fondos') NOT NULL DEFAULT 'Socio',
                `join_date` DATE NOT NULL,
                `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `partner_name_index` (`partner_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Insertar datos iniciales
        $pdo->exec("
            INSERT INTO `partner_benefits` 
                (`partner_name`, `percentage`, `role`, `join_date`)
            VALUES 
                ('FERNANDO CHALE', 18.00, 'Principal', '2023-01-01'),
                ('MARIA CARMEN NSUE', 18.00, 'Principal', '2023-01-01'),
                ('GENEROSA ABEME', 30.00, 'Principal', '2023-01-01'),
                ('MARIA ISABEL', 8.00, 'Socio', '2023-01-01'),
                ('CAJA', 16.00, 'Caja', '2023-01-01'),
                ('FONDOS DE SOCIOS', 10.00, 'Fondos', '2023-01-01')
        ");
    }

    // Verificar si la tabla partner_payments existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'partner_payments'");
    $paymentsTableExists = $stmt->rowCount() > 0;

    if (!$paymentsTableExists) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `partner_payments` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `partner_name` VARCHAR(100) NOT NULL,
                `amount` DECIMAL(15,2) NOT NULL,
                `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `confirmed` BOOLEAN DEFAULT FALSE,
                `confirmation_date` TIMESTAMP NULL,
                `notes` TEXT,
                FOREIGN KEY (`partner_name`) REFERENCES `partner_benefits`(`partner_name`),
                INDEX `payment_date_index` (`payment_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    echo json_encode(['success' => true, 'message' => 'Estructura de base de datos verificada y corregida']);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

# Opened Files
## File Name
sql\update_partner_benefits.sql
## File Content
USE `abeme_modjobuy`;

-- Tabla de beneficios de socios
CREATE TABLE IF NOT EXISTS `partner_benefits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `partner_name` VARCHAR(100) NOT NULL UNIQUE,
    `percentage` DECIMAL(5,2) NOT NULL,
    `total_earnings` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Total histórico de ganancias generadas',
    `current_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Balance actual después de pagos',
    `role` ENUM('Principal', 'Socio', 'Caja', 'Fondos') NOT NULL DEFAULT 'Socio',
    `join_date` DATE NOT NULL,
    `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `partner_name_index` (`partner_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de pagos a socios
CREATE TABLE IF NOT EXISTS `partner_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `partner_name` VARCHAR(100) NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `payment_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `confirmed` BOOLEAN DEFAULT FALSE,
    `confirmation_date` TIMESTAMP NULL,
    `notes` TEXT,
    FOREIGN KEY (`partner_name`) REFERENCES `partner_benefits`(`partner_name`),
    INDEX `payment_date_index` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar datos iniciales de socios
INSERT INTO `partner_benefits` 
    (`partner_name`, `percentage`, `role`, `join_date`)
VALUES 
    ('FERNANDO CHALE', 18.00, 'Principal', '2023-01-01'),
    ('MARIA CARMEN NSUE', 18.00, 'Principal', '2023-01-01'),
    ('GENEROSA ABEME', 30.00, 'Principal', '2023-01-01'),
    ('MARIA ISABEL', 8.00, 'Socio', '2023-01-01'),
    ('CAJA', 16.00, 'Caja', '2023-01-01'),
    ('FONDOS DE SOCIOS', 10.00, 'Fondos', '2023-01-01')
ON DUPLICATE KEY UPDATE
    percentage = VALUES(percentage),
    role = VALUES(role);

-- Trigger para actualizar total_earnings cuando se confirma un nuevo pago
DELIMITER //
CREATE TRIGGER update_partner_earnings_after_payment
AFTER UPDATE ON partner_payments
FOR EACH ROW
BEGIN
    IF NEW.confirmed = 1 AND OLD.confirmed = 0 THEN
        UPDATE partner_benefits
        SET 
            current_balance = current_balance - NEW.amount
        WHERE partner_name = NEW.partner_name;
    END IF;
END;
//
DELIMITER ;

-- Procedimiento para actualizar las ganancias totales de los socios
DELIMITER //
CREATE PROCEDURE update_partner_total_earnings()
BEGIN
    -- Calcular beneficio base total de envíos entregados
    DECLARE total_base_profit DECIMAL(15,2);
    SELECT SUM(weight * 2500) INTO total_base_profit
    FROM shipments 
    WHERE status = 'delivered';
    
    -- Obtener ingresos adicionales
    DECLARE additional_profit DECIMAL(15,2);
    SELECT COALESCE(SUM(amount), 0) INTO additional_profit
    FROM expenses 
    WHERE operation_type = 'add';
    
    -- Calcular beneficio total
    SET total_base_profit = COALESCE(total_base_profit, 0) + COALESCE(additional_profit, 0);
    
    -- Actualizar ganancias totales de cada socio
    UPDATE partner_benefits
    SET total_earnings = (total_base_profit * (percentage / 100));
END;
//
DELIMITER ;

-- Trigger para actualizar las ganancias totales cuando se marca un envío como entregado
DELIMITER //
CREATE TRIGGER update_earnings_after_delivery
AFTER UPDATE ON shipments
FOR EACH ROW
BEGIN
    IF NEW.status = 'delivered' AND OLD.status != 'delivered' THEN
        CALL update_partner_total_earnings();
    END IF;
END;
//
DELIMITER ;

# Opened Files
## File Name
api\confirm_partner_payment.php
## File Content
<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Verificar que el usuario esté autenticado
requireAuth();

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);
$partnerName = $data['partnerName'] ?? '';
$amount = $data['amount'] ?? 0;
$notes = $data['notes'] ?? '';

if (!$partnerName || !$amount) {
    echo json_encode(['error' => 'Faltan datos requeridos']);
    exit;
}

try {
    // Iniciar transacción
    $pdo->beginTransaction();

    // Verificar que el socio existe y tiene suficiente balance
    $stmt = $pdo->prepare("
        SELECT current_balance 
        FROM partner_benefits 
        WHERE partner_name = ? 
        AND current_balance >= ?
    ");
    $stmt->execute([$partnerName, $amount]);
    
    if (!$stmt->fetch()) {
        throw new Exception('Balance insuficiente o socio no encontrado');
    }

    // Registrar el pago
    $stmt = $pdo->prepare("
        INSERT INTO partner_payments 
        (partner_name, amount, confirmed, confirmation_date, notes) 
        VALUES (?, ?, 1, NOW(), ?)
    ");
    $stmt->execute([$partnerName, $amount, $notes]);

    // El trigger se encargará de actualizar el balance actual del socio

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Pago confirmado exitosamente'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}

# Opened Files
## File Name
setup_test_data_pdo.php
## File Content
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

# Opened Files
## File Name
setup_test_data.php
## File Content
<?php
// Conexión directa a MySQL
$conn = new mysqli('localhost', 'root', '', 'abeme_modjobuy');
if ($conn->connect_error) {
    die('No se pudo conectar: ' . $conn->connect_error);
}
$conn->set_charset("utf8");

// Limpiar datos existentes
$conn->query("DELETE FROM shipments WHERE code LIKE 'TEST%'") or die($conn->error);
$conn->query("DELETE FROM expenses WHERE description LIKE 'TEST%'") or die($conn->error);
$conn->query("UPDATE partner_benefits SET total_benefits = 0, total_expenses = 0, current_balance = 0") or die($conn->error);
$conn->query("UPDATE system_metrics SET metric_value = 0 WHERE metric_name = 'total_accumulated_benefits'") or die($conn->error);

// Insertar envíos
$query = "INSERT INTO shipments (code, group_code, sender_name, sender_phone, receiver_name, receiver_phone, product, weight, shipping_cost, sale_price, advance_payment, profit, ship_date, est_date, status) VALUES
    ('TEST-001', 'agosto-14-25', 'Juan Pérez', '123456789', 'María García', '987654321', 'Ropa', 15.5, 15000, 100750, 50000, 38750, '2025-08-01', '2025-08-15', 'delivered'),
    ('TEST-002', 'agosto-14-25', 'Ana Martínez', '456789123', 'Pedro Sánchez', '321654987', 'Electrónicos', 8.2, 8000, 53300, 30000, 20500, '2025-08-02', '2025-08-16', 'delivered'),
    ('TEST-003', 'agosto-14-25', 'Carlos López', '789123456', 'Laura Torres', '654987321', 'Alimentos', 25.0, 25000, 162500, 80000, 62500, '2025-08-03', '2025-08-17', 'delivered')";
$conn->query($query) or die($conn->error);

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

# Opened Files
## File Name
populate_test_data.php
## File Content
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

# Opened Files
## File Name
css\archived-shipments.css
## File Content
.archived-groups {
    padding: 1rem;
    max-width: 1200px;
    margin: 0 auto;
}

.shipment-group {
    margin-bottom: 2rem;
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.group-header {
    background: #1976d2;
    padding: 1rem;
    border-radius: 8px 8px 0 0;
    cursor: pointer;
}

.group-header h2 {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin: 0;
    font-size: 1.2rem;
    color: #ffffff;
}

.group-toggle {
    display: inline-block;
    transition: transform 0.3s ease;
}

.group-header.active .group-toggle {
    transform: rotate(180deg);
}

.group-date {
    color: var(--text);
    font-size: 0.9rem;
    margin-left: auto;
}

.total-shipments {
    background: var(--primary);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.9rem;
}

.shipments-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
    padding: 1rem;
    max-height: 500px;
    overflow-y: auto;
}

.shipments-grid::-webkit-scrollbar {
    width: 6px;
}

.shipments-grid::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.shipments-grid::-webkit-scrollbar-thumb {
    background: #1976d2;
    border-radius: 3px;
}

.shipments-grid::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.shipments-grid::-webkit-scrollbar-track {
    background: var(--gray-light);
    border-radius: 3px;
}

.shipments-grid::-webkit-scrollbar-thumb {
    background-color: var(--primary);
    border-radius: 3px;
}

.shipment-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.shipment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border-color);
}

.shipment-code {
    font-weight: 600;
    color: #1976d2;
}

.status-indicator {
    font-size: 0.8rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    background: #e8f5e9;
    color: #2e7d32;
}

.status-indicator.delivered {
    background: #e8f5e9;
    color: #2e7d32;
}

.shipment-details {
    display: grid;
    gap: 0.75rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.detail-item label {
    color: #555555;
    font-weight: 500;
}

.detail-item span {
    font-weight: 500;
    color: #333333;
}

.shipment-footer {
    margin-top: 1rem;
    padding-top: 0.5rem;
    border-top: 1px solid #dee2e6;
    font-size: 0.85rem;
    color: #666666;
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    .archived-groups {
        padding: 0.5rem;
    }

    .shipments-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 0.5rem;
        padding: 0.5rem;
    }

    .group-header h2 {
        font-size: 1rem;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .group-date, .total-shipments {
        font-size: 0.8rem;
    }

    .shipment-card {
        padding: 0.75rem;
    }

    .detail-item {
        font-size: 0.85rem;
    }
}

/* For screens smaller than 480px */
@media (max-width: 480px) {
    .shipments-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    }
}

# Opened Files
## File Name
css\benefits-app.css
## File Content
/* Configuración base */
:root {
    --primary-color: #1976d2;
    --secondary-color: #2196f3;
    --success-color: #4caf50;
    --background-color: #f5f5f5;
    --surface-color: #ffffff;
    --text-primary: #333333;
    --text-secondary: #666666;
    --spacing-unit: 0.5rem;
    --header-height: 85px;
    --radius-card: 12px;
    --shadow-card: 0 2px 8px rgba(0,0,0,0.1);
    --chart-height: 400px;
}

/* Reset y configuración base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    -webkit-tap-highlight-color: transparent;
}

body {
    font-family: 'Roboto', sans-serif;
    background: var(--background-color);
    color: var(--text-primary);
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
    margin: 0;
    padding: 0;
    padding-top: calc(var(--header-height) + var(--spacing-unit));
}

/* Header fijo */
.header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: var(--primary-color);
    color: white;
    z-index: 1000;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: var(--spacing-unit) 0;
}

.nav-container {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    padding: 0 var(--spacing-unit);
    gap: var(--spacing-unit);
    max-width: 100%;
}

.logo {
    text-align: center;
}

.logo-img {
    height: 40px;
}

.header-title {
    text-align: center;
}

.header-title h1 {
    font-size: 1.1rem;
    font-weight: 500;
    margin: 0;
}

.header-title p {
    font-size: 0.75rem;
    opacity: 0.9;
    margin: 0;
}

.back-button {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: var(--radius-card);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background-color 0.2s;
}

.back-button:hover {
    background: rgba(255, 255, 255, 0.2);
}

.back-button i {
    font-size: 0.9rem;
}

/* Estilos para el modal de detalles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100vh;
    background-color: rgba(0, 0, 0, 0.5);
    -webkit-backdrop-filter: blur(4px);
    backdrop-filter: blur(4px);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    position: relative;
    background-color: var(--surface-color);
    padding: 0;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    border-radius: var(--radius-card);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    animation: modalFadeIn 0.2s ease-out;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
    color: var(--text-primary);
}

.close {
    color: var(--text-secondary);
    font-size: 1.5rem;
    font-weight: bold;
    cursor: pointer;
    padding: 0.5rem;
    margin: -0.5rem;
    transition: color 0.2s;
}

.close:hover {
    color: var(--text-primary);
}

.modal-body {
    padding: 1.5rem;
}

/* Estilos para los detalles del socio */
.partner-details {
    color: var(--text-primary);
}

.loading-spinner {
    text-align: center;
    padding: 2rem;
    color: var(--text-secondary);
}

.loading-spinner i {
    font-size: 2rem;
    margin-bottom: 1rem;
}

.error-message {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #dc3545;
    padding: 1rem;
    background: #fff5f5;
    border-radius: var(--radius-card);
}

.partner-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.partner-avatar {
    font-size: 3rem;
    color: var(--primary-color);
}

.partner-main-info h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.5rem;
}

.partner-role {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
}

.detail-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: var(--radius-card);
    transition: transform 0.2s;
}

.detail-item.highlight {
    background: #e3f2fd;
}

.detail-item:hover {
    transform: translateY(-2px);
}

.detail-icon {
    color: var(--primary-color);
    font-size: 1.5rem;
}

.detail-content {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.detail-label {
    color: var(--text-secondary);
    font-size: 0.85rem;
}

.detail-value {
    font-weight: 500;
    font-size: 1rem;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
}

/* Estilos para el gráfico de distribución */
.chart-section {
    background: var(--surface-color);
    border-radius: var(--radius-card);
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-card);
}

.distribution-chart {
    height: var(--chart-height);
    width: 100%;
    max-width: 800px;
    margin: 0 auto;
    position: relative;
}

/* Estilos para los botones de acción */
.action-button {
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: var(--radius-card);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background-color 0.2s, transform 0.1s;
}

.action-button:hover {
    background: var(--secondary-color);
    transform: translateY(-1px);
}

.action-button:active {
    transform: translateY(0);
}

.action-button i {
    font-size: 0.9rem;
}

/* Contenido principal */
.main-content {
    padding: var(--spacing-unit);
    max-width: 100%;
    margin: 0 auto;
    overflow-x: hidden;
}

/* Tarjetas de estadísticas */
.stats-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: calc(var(--spacing-unit) * 1.5);
    margin-bottom: calc(var(--spacing-unit) * 3);
}

.stat-card {
    background: var(--surface-color);
    border-radius: var(--radius-card);
    padding: calc(var(--spacing-unit) * 1.5);
    box-shadow: var(--shadow-card);
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: transform 0.2s;
}

.stat-card:active {
    transform: scale(0.98);
}

.stat-icon {
    font-size: 1.5rem;
    color: var(--primary-color);
    margin-bottom: var(--spacing-unit);
}

.stat-info h3 {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-bottom: calc(var(--spacing-unit) * 0.5);
    font-weight: normal;
}

.stat-value {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
}

/* Sección de distribución */
.benefits-distribution {
    background: var(--surface-color);
    border-radius: var(--radius-card);
    padding: calc(var(--spacing-unit) * 2);
    margin-bottom: calc(var(--spacing-unit) * 3);
    box-shadow: var(--shadow-card);
}

.benefits-distribution h2 {
    color: var(--text-primary);
    font-size: 1.2rem;
    margin-bottom: calc(var(--spacing-unit) * 2);
}

.partners-progress {
    display: flex;
    flex-direction: column;
    gap: calc(var(--spacing-unit) * 1.5);
}

.partner-progress-card {
    background: white;
    border-radius: calc(var(--radius-card) / 2);
    padding: calc(var(--spacing-unit) * 1.5);
    transition: transform 0.2s;
}

.partner-progress-card:active {
    transform: scale(0.99);
}

.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: var(--spacing-unit);
}

.progress-info {
    flex: 1;
}

.partner-name {
    font-size: 1rem;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.3rem;
    display: block;
}

.progress-stats {
    display: flex;
    align-items: center;
    gap: calc(var(--spacing-unit) * 0.8);
    margin-top: 0.2rem;
}

.percentage-badge {
    background: var(--primary-color);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 1rem;
    font-size: 0.8rem;
    font-weight: 500;
}

.amount {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.main-partner-tag {
    background: #e3f2fd;
    color: var(--primary-color);
    padding: 0.2rem 0.6rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 500;
}

.progress-container {
    margin-top: calc(var(--spacing-unit) * 1.2);
}

.progress-track {
    background: #f0f0f0;
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
    position: relative;
}

.progress-fill {
    height: 100%;
    border-radius: 4px;
    position: relative;
    transition: width 1s ease-in-out;
}

.progress-glow {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    width: 15px;
    filter: blur(3px);
    opacity: 0.7;
}

/* Tabla de beneficios */
.detailed-view {
    background: var(--surface-color);
    border-radius: var(--radius-card);
    overflow: hidden;
    margin-bottom: calc(var(--spacing-unit) * 3);
}

.detailed-view h2 {
    padding: calc(var(--spacing-unit) * 1.5);
    margin: 0;
    font-size: 1.1rem;
    background: var(--primary-color);
    color: white;
}

.benefits-table {
    width: 100%;
}

.benefits-table thead {
    display: none;
}

.benefits-table tbody tr {
    display: flex;
    flex-direction: column;
    padding: calc(var(--spacing-unit) * 1.5);
    border-bottom: 1px solid rgba(0,0,0,0.08);
    background: var(--surface-color);
}

.benefits-table tr:last-child {
    border-bottom: none;
}

.partner-info {
    display: flex;
    align-items: center;
    gap: var(--spacing-unit);
    margin-bottom: var(--spacing-unit);
}

.partner-name {
    font-size: 1.1rem;
    font-weight: 500;
    color: var(--text-primary);
}

.partner-percentage {
    background: var(--primary-color);
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 1rem;
    font-size: 0.9rem;
}

.main-partner-badge {
    background: #e3f2fd;
    color: var(--primary-color);
    padding: 0.2rem 0.6rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 500;
}

.partner-amount {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--primary-color);
    margin: calc(var(--spacing-unit) * 0.5) 0 var(--spacing-unit) 0;
}

/* Botones */
.btn-group {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: calc(var(--spacing-unit) * 0.75);
    padding-top: var(--spacing-unit);
    border-top: 1px solid rgba(0,0,0,0.05);
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.7rem 0.5rem;
    border-radius: calc(var(--radius-card) / 2);
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    transition: transform 0.2s, opacity 0.2s;
}

.btn:active {
    transform: scale(0.98);
}

.btn i {
    font-size: 1rem;
}

.btn {
    padding: calc(var(--spacing-unit) * 0.75);
    border-radius: calc(var(--radius-card) / 2);
    border: none;
    font-size: 0.85rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: calc(var(--spacing-unit) * 0.5);
    cursor: pointer;
    transition: transform 0.2s, opacity 0.2s;
}

.btn:active {
    transform: scale(0.98);
    opacity: 0.9;
}

.btn i {
    font-size: 1rem;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-success {
    background: var(--success-color);
    color: white;
}

.btn-info {
    background: var(--secondary-color);
    color: white;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    -webkit-backdrop-filter: blur(4px);
    backdrop-filter: blur(4px);
    z-index: 2000;
    opacity: 0;
    transition: opacity 0.2s ease;
}

.modal.show {
    display: block;
    opacity: 1;
}

.modal-content {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(0.7);
    width: 90%;
    max-width: 600px;
    max-height: 85vh;
    background: var(--surface-color);
    border-radius: var(--radius-card);
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    overflow-y: auto;
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.modal.show .modal-content {
    transform: translate(-50%, -50%) scale(1);
}

.close {
    position: absolute;
    top: var(--spacing-unit);
    right: var(--spacing-unit);
    font-size: 1.5rem;
    color: var(--text-secondary);
    cursor: pointer;
    padding: calc(var(--spacing-unit) * 0.5);
}

/* Badges y etiquetas */
.main-partner-badge {
    display: inline-block;
    background: #e3f2fd;
    color: var(--primary-color);
    padding: calc(var(--spacing-unit) * 0.25) calc(var(--spacing-unit) * 0.5);
    border-radius: calc(var(--radius-card) / 4);
    font-size: 0.7rem;
    margin-top: calc(var(--spacing-unit) * 0.5);
}

/* Gráfico de distribución */
.distribution-chart {
    margin: 1.5rem 0 2.5rem;
    position: relative;
    height: 300px;
    width: 100%;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
    background: #fff;
    padding: 1rem;
    border-radius: var(--radius-card);
    box-shadow: var(--shadow-card);
}

.distribution-chart canvas {
    width: 100% !important;
    height: 100% !important;
}

/* Animaciones */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.fade-in {
    animation: fadeIn 0.3s ease-out;
}

# Opened Files
## File Name
css\benefits-new.css
## File Content
/* Estilos generales */
body {
    font-family: 'Roboto', sans-serif;
    margin: 0;
    padding: 0;
    background: #f5f5f5;
}

/* Contenedor principal */
.main-content {
    padding: 1rem;
    max-width: 100%;
}

/* Tarjetas de estadísticas */
.stats-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 0 -0.25rem;
    width: calc(100% + 0.5rem);
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 0.8rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
    flex: 1 1 calc(33.333% - 0.5rem);
    min-width: calc(33.333% - 0.5rem);
    max-width: calc(33.333% - 0.5rem);
    box-sizing: border-box;
    margin: 0;
}

.stat-icon {
    font-size: 1.2rem;
    color: #1976d2;
    margin-bottom: 0.3rem;
}

.stat-info h3 {
    font-size: 0.8rem;
    margin: 0;
    color: #666;
}

.stat-value {
    font-size: 0.9rem;
    font-weight: bold;
    margin: 0.3rem 0 0;
}

/* Tabla responsiva */
.table-responsive {
    margin: 1rem -0.5rem;
    overflow-x: visible;
}

.benefits-table {
    width: 100%;
    border-collapse: collapse;
}

.benefits-table thead {
    display: none;
}

.benefits-table tr {
    display: block;
    margin-bottom: 1rem;
    background: white;
    border-radius: 8px;
    padding: 0.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.benefits-table td {
    display: flex;
    padding: 0.5rem;
    border: none;
    align-items: center;
}

.benefits-table td:before {
    content: attr(data-label);
    font-weight: bold;
    width: 120px;
    flex-shrink: 0;
}

.btn-group {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    margin-top: 0.5rem;
}

.btn {
    flex: 1 1 auto;
    text-align: center;
    font-size: 0.8rem;
    padding: 0.4rem;
    border-radius: 4px;
    border: none;
    cursor: pointer;
}

.btn-primary { background: #1976d2; color: white; }
.btn-success { background: #4caf50; color: white; }
.btn-info { background: #2196f3; color: white; }

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    display: none;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    padding: 1rem;
    border-radius: 8px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    margin: 5vh auto;
}

/* Badges y etiquetas */
.main-partner-badge {
    display: inline-block;
    background: #e3f2fd;
    color: #1976d2;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    font-size: 0.7rem;
    margin-top: 0.2rem;
}

# Opened Files
## File Name
api\partner_details.php
## File Content
<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/partner_earnings.php';

header('Content-Type: application/json');

try {
    $partner = $_GET['partner'] ?? '';
    if (!$partner) {
        throw new Exception('Socio no especificado');
    }

    // Actualizar los beneficios del socio
    $updateResult = updatePartnerBenefits($partner);
    if (!$updateResult['success']) {
        throw new Exception('Error al actualizar beneficios: ' . $updateResult['error']);
    }

    // Obtener los datos actualizados del socio
    $stmt = $pdo->prepare("
        SELECT 
            partner_name,
            percentage,
            role,
            join_date,
            total_earnings,
            current_balance,
            last_updated
        FROM partner_benefits 
        WHERE partner_name = ?
    ");

    $stmt->execute([$partner]);
    $partnerData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$partnerData) {
        throw new Exception('Socio no encontrado');
    }

    // Obtener el último pago
    $stmt = $pdo->prepare("
        SELECT 
            amount,
            payment_date,
            confirmation_date
        FROM partner_payments 
        WHERE partner_name = ? 
        AND confirmed = 1 
        ORDER BY payment_date DESC 
        LIMIT 1
    ");
    $stmt->execute([$partner]);
    $lastPayment = $stmt->fetch(PDO::FETCH_ASSOC);

    // Obtener historial de pagos
    $stmt = $pdo->prepare("
        SELECT 
            amount,
            payment_date,
            confirmation_date,
            confirmed,
            previous_balance,
            new_balance,
            notes
        FROM partner_payments 
        WHERE partner_name = ?
        ORDER BY payment_date DESC
    ");
    $stmt->execute([$partner]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener resumen de pagos
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_payments,
            SUM(CASE WHEN confirmed = 1 THEN amount ELSE 0 END) as total_confirmed,
            SUM(CASE WHEN confirmed = 0 THEN amount ELSE 0 END) as total_pending
        FROM partner_payments 
        WHERE partner_name = ?
    ");
    $stmt->execute([$partner]);
    $paymentSummary = $stmt->fetch(PDO::FETCH_ASSOC);

    // Estructura de respuesta
    $response = [
        'name' => $partnerData['partner_name'],
        'role' => $partnerData['role'],
        'joinDate' => $partnerData['join_date'],
        'percentage' => $partnerData['percentage'],
        'totalEarnings' => $partnerData['total_earnings'],
        'currentBalance' => $partnerData['current_balance'],
        'lastPayment' => $lastPayment ? [
            'date' => $lastPayment['payment_date'],
            'amount' => $lastPayment['amount'],
            'confirmationDate' => $lastPayment['confirmation_date']
        ] : null,
        'lastUpdated' => $partnerData['last_updated'],
        'paymentHistory' => [
            'payments' => $payments,
            'summary' => [
                'totalPayments' => (int)$paymentSummary['total_payments'],
                'totalConfirmed' => (float)$paymentSummary['total_confirmed'],
                'totalPending' => (float)$paymentSummary['total_pending']
            ]
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener los datos del socio']);
    error_log("Error en partner_details.php: " . $e->getMessage());
}

